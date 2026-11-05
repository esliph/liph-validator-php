---
name: bench
description: Measures esliph/validator runtime and memory with PHPBench and compares the working tree against a baseline (a git ref, by default HEAD, or a saved tag), before/after style, telling real effects apart from noise.
argument-hint: "[<git-ref> | tag:<name> | save:<name>] [phpbench options, e.g. --filter=Union]"
disable-model-invocation: true
---

# Bench

Answers "did this change make the library faster or slower, and by how much?" with numbers taken on the same machine, by the same benchmark code, back to back. The only thing allowed to differ between the two sides of a comparison is the library code.

## Boundary

**Territory** — Decides what a change is measured against, which subjects run, and whether an observed difference is an effect or noise. Owns the benchmark suite in `benchmarks/` (which scenarios exist, their sizes, their sanity checks) and the PHPBench configuration in `phpbench.json`.

**Out of bounds** — Predicting from the code whether a diff should cost more (a static cost review of the change); editing `src/` to act on what the numbers show; how commands execute on this machine (the canonical forms are in the Commands section of `AGENTS.md`); installing dependencies; deciding whether a measured regression is acceptable — that is the person's call.

**What I don't need to know** — Why the change was made or whether it is correct: the test suite decides correctness, and a benchmark of broken code is still a valid measurement of that code. Absolute production numbers: the container runs without opcache/JIT under Docker virtualization, so only ratios within one session mean anything.

**Edge contract** — Receives `$ARGUMENTS`: an optional baseline selector and optional PHPBench options passed through verbatim. Delivers a comparison per subject and size (baseline time, current time, Δ time, Δ memory, rstdev, verdict), plus the complexity reading when sizes change the picture.

## Pieces

- `benchmarks/*Bench.php` — the subjects. Each class targets one cost; each subject runs over a size ParamProvider whose steps grow by a constant factor, so the complexity class shows up in the curve, not in one point.
- `benchmarks/bootstrap.php` — when `BENCH_SRC` points at a directory, library classes load from there instead of `src/`. This is what lets the *current* benchmark code measure *old* library code without touching the working tree.
- `phpbench.json` — revs, iterations, warmup, retry threshold, and the storage of tagged runs (`cache/phpbench`, machine-local and gitignored: a baseline recorded on another machine or day is not a baseline).

## Arguments

| `$ARGUMENTS` starts with | Meaning |
|---|---|
| nothing, or only `--options` | baseline = `HEAD`, re-measured now |
| `<git-ref>` (sha, branch, `HEAD~3`) | baseline = `src/` at that ref, re-measured now |
| `tag:<name>` | baseline = a run saved earlier with that tag |
| `save:<name>` | measure the working tree and save it under that tag; no comparison |

Everything that starts with `--` is forwarded to `phpbench run` on both sides (typically `--filter=<subject or class>`). Use the same options on both sides — a comparison across different revs or filters is not a comparison.

## Workflow

### 1. Preconditions

- `vendor/bin/phpbench` must exist. If it does not, say that dependencies are not installed and stop — do not install them.
- For a git-ref baseline, check that `src/` actually differs from the ref: `git diff --quiet <ref> -- src` plus `git ls-files --others --exclude-standard src` for new files. If nothing differs, there is nothing to compare: say so and offer `save:<name>` instead. Changes only in `benchmarks/` do not count — both sides always run the working-tree benchmarks.

### 2. Measure the baseline

**From a git ref** (the default). Re-measure the baseline in this session instead of reusing an old tag: machine load, the Docker VM and CPU frequency drift between sessions by more than the effects worth detecting.

```sh
SHA=$(git rev-parse --short <ref>)
rm -rf cache/bench/$SHA && mkdir -p cache/bench/$SHA
git archive $SHA src | tar -x -C cache/bench/$SHA
```

Then run the suite with `BENCH_SRC=cache/bench/$SHA/src`, the user's options, `--tag=base_$SHA` and `--progress=none`. Keep this run's table: it holds the baseline values.

If the run dies with "Class … does not exist in BENCH_SRC", a subject uses API that did not exist at that ref. Exclude it with `--filter` on both sides and report it as not comparable.

**From a tag.** Skip to step 3 with `--ref=<name>`, and state in the report that the baseline comes from another session.

### 3. Measure the working tree

Run the suite without `BENCH_SRC`, with the same options, `--ref=<baseline tag>` and `--progress=none`. The aggregate report then prints each value followed by its difference to the baseline (`2.274ms -1.73%`).

### 4. Decide what is real

Read the numbers before writing a verdict:

- **Noise floor.** `rstdev` above 5% on either side means the measurement is noisy — PHPBench already retried iterations over the threshold, so persistent dispersion is outside interference. Re-run just those subjects (`--filter`) on both sides before concluding.
- **Effect.** Call a time difference real only when |Δ| exceeds about twice the larger `rstdev` of the two sides; below that the verdict is **noise**, whatever the sign. Small effects (< 3%) deserve a second pair of runs before being reported as real.
- **Complexity.** Compare consecutive sizes within one run: with sizes growing 10× per step, a time ratio near 10× is linear and near 100× is quadratic (100× per step: ~100× linear, flat O(1)). A change that bends the curve is stronger evidence than a percentage on a single size, and it is what tells "faster constant" apart from "better algorithm".
- **Memory.** `mem_peak` includes the subject's input, so read differences in absolute terms against the input size. Unlike time, it barely varies between runs — any difference is real, but may be immaterial.

### 5. Report

```
Baseline: <ref> (<sha>) re-measured now | tag <name> from another session
Options: <phpbench options, or "full suite">

| subject | set | baseline | current | Δ time | Δ mem | rstdev (b/c) | verdict |
|---|---|---|---|---|---|---|---|

Complexity: <only for subjects whose curve changed — ratios per step before and after>
Not comparable: <subjects excluded and why, if any>
```

Order rows by |Δ time| among real effects, then noise. Verdicts: **faster**, **slower**, **noise**. Close with one line on the environment caveat when the effects are small, and leave the decision about the change to the person.

## Writing benchmarks

A change that touches a path no subject exercises needs a subject before the measurement means anything. Since the baseline side loads only the library from `BENCH_SRC`, a benchmark written *after* the change still measures the old code — order does not matter, it only has to exist in the working tree.

- One class per cost, named `<Cost>Bench`, in `Esliph\Validator\Benchmarks\`. The class docblock says which cost it targets and how to read its curve.
- Build the schema and the input in a `#[Bench\BeforeMethods]` method, never inside the subject — otherwise construction is timed too. Exception: when construction *is* the cost (fluent chains), build inside the subject and lower `#[Bench\Revs]` if a single rev is slow.
- Guard every scenario in its setup with `Scenario::expectSuccess` / `Scenario::expectIssues`: a subject named "invalid" that accidentally passes measures the wrong path and still prints a plausible number.
- Use at least three sizes spaced by a constant factor, so the complexity class is readable.
- Do not rename existing subjects or set names without reason: tagged runs are matched by benchmark, subject and set name, so a rename silently drops the comparison with saved baselines.
- The benchmarks are part of the static analysis — they must pass it like `src/` does.

Smoke-test a new subject with `--filter=<it> --revs=2 --iterations=1` before a real run.
