# Dorra database performance report

Measured 2026-10-28. These are observations on this local machine, not production guarantees.

## Reproduce

```powershell
php scripts/benchmark-database.php --runs=20 --warmup=3
```

Requires the existing local MySQL database with exactly 100,000 designs and the three named indexes. No migration, seeding, deletion, index dropping, or server restart is performed. The script uses a read-only REPEATABLE READ transaction, checks both variants return identical results, and compares a SHA-256 fingerprint of every design field before/after inside the same snapshot. It rolls the transaction back even if measurement fails. An optional `--output=path.json` retains a separate run.

## Environment and method

- PHP 8.4.12; Laravel 12.70.2; MySQL 8.4.3, InnoDB, Windows x86_64.
- CPU: Intel(R) Xeon(R) CPU E5-1650 v4 @ 3.60GHz, 6 cores / 12 threads; RAM 31.92 GiB.
- MySQL buffer pool 128 MiB; connection utf8mb4_unicode_ci, UTC. Single local TCP client; no load generator or controlled concurrency.
- Table statistics: data 11.52 MiB; indexes 17.58 MiB. These allocation statistics are not an exact working-set or per-index size measurement.
- Each query variant receives 3 discarded warmup runs and 20 measured runs. Pair order alternates each round. Median and nearest-rank P95 are reported; raw min/max and every sample are retained.
- Timings use PHP hrtime around Laravel PDO SELECT and fetching the result. They include client dispatch, local transport and decoding, not only server CPU. No Blade, browser, image, or model hydration time is included. EXPLAIN ANALYZE contains separate server-instrumented iterator timings and is run outside the measured samples.
- Final database run was made with Playwright idle. Other local/background workloads were not isolated, so results have environmental variance.

### Baseline and cache conditions

The schema already had indexes. Baseline SQL adds `IGNORE INDEX (idx_designs_category_status_created, idx_designs_title, idx_designs_created_id)`; indexed SQL omits that hint. Both variants read the same snapshot and keep the primary key. This is a reproducible before/after **access-path** comparison, not a physical index drop/rebuild benchmark. It does not measure insert/update cost, index construction, disk size reduction, or differences in physical table layout. No additional candidate index was created.

A complete fingerprint scan warms table pages, then each variant receives explicit warmups. No buffer pool, OS cache, or browser cache was flushed. This is a warm-cache experiment, not a cold-start test; MySQL 8 has no query result cache. Global buffer counters bracket all measured queries, plans and warmups; counters may also include unrelated sessions.

Observed buffer pool physical reads: 1106 ? 1106 (delta 0); logical read requests delta 1,627,879. This supports the warm-buffer interpretation but does not control every system cache.

Snapshot rows: 100,000; unchanged: True. Fingerprint: `70bb707b256ad97266c2d42a100b0c36284f25121c2ee5236e72efa2f18d582e`. The snapshot fixes data even if another connection writes; this is not a global audit of other sessions.

## Results

All values in milliseconds. Ratio = baseline median / indexed median; below 1 means the indexed variant was slower in this run. Small ratios and sub-millisecond differences should not be generalized without more runs.

| Query | Baseline median | Indexed median | Baseline P95 | Indexed P95 | Ratio |
| --- | ---: | ---: | ---: | ---: | ---: |
| newest_listing | 86.505 | 0.991 | 112.180 | 1.265 | 87.26? |
| newest_count | 9.544 | 17.095 | 12.021 | 17.479 | 0.56? |
| category_status_listing | 47.834 | 1.036 | 75.304 | 1.207 | 46.15? |
| category_status_count | 27.756 | 1.925 | 28.079 | 2.340 | 14.42? |
| category_only_listing | 45.075 | 1.328 | 71.655 | 2.634 | 33.95? |
| category_only_count | 20.974 | 2.646 | 40.120 | 7.164 | 7.93? |
| status_only_listing | 60.700 | 0.841 | 85.286 | 1.350 | 72.20? |
| status_only_count | 29.835 | 25.981 | 50.308 | 37.440 | 1.15? |
| english_prefix_listing | 48.321 | 1.217 | 72.955 | 2.436 | 39.69? |
| english_prefix_count | 26.117 | 5.075 | 26.703 | 14.978 | 5.15? |
| arabic_prefix_listing | 51.738 | 0.968 | 79.957 | 1.777 | 53.42? |
| arabic_prefix_count | 27.157 | 11.051 | 30.578 | 11.207 | 2.46? |
| combined_listing | 44.903 | 1.095 | 61.624 | 2.106 | 41.02? |
| combined_count | 31.761 | 8.084 | 56.733 | 16.435 | 3.93? |
| rare_prefix_listing | 47.368 | 0.643 | 64.404 | 0.834 | 73.64? |
| rare_prefix_count | 27.421 | 0.568 | 27.773 | 0.674 | 48.25? |
| absent_prefix_listing | 45.605 | 0.437 | 49.168 | 0.536 | 104.36? |
| absent_prefix_count | 25.739 | 0.520 | 33.889 | 0.624 | 49.48? |
| deep_offset_listing | 117.026 | 125.223 | 147.118 | 140.674 | 0.93? |

Listing queries select all design columns, order by created_at DESC then id DESC, and LIMIT 24. Counts are measured separately because Laravel paginate also executes COUNT. Categories/eager-load queries and page rendering are excluded. Do not add medians and call the result a measured page latency.

Conditions: category_id=3; status=published; English prefix Modern%; Arabic prefix تصميم%; combined uses English prefix plus category/status; rare prefix Modern Business Card 9999% (1 match); absent prefix NoSuchDesignPrefix% (0 matches); deep offset uses OFFSET 96000. No SQL interpolation of user data is used; bindings are in the raw report.

## SQL and query plans

Exact baseline/indexed SQL, bindings, tabular EXPLAIN, EXPLAIN ANALYZE, result digests and all sample values are in [performance-results.json](performance-results.json). The script is the source for every query condition. These examples show the access-path comparison:

```sql
SELECT * FROM designs IGNORE INDEX (idx_designs_category_status_created, idx_designs_title, idx_designs_created_id)
WHERE category_id = 3 AND status = 'published'
ORDER BY created_at DESC, id DESC LIMIT 24 OFFSET 0;

SELECT * FROM designs WHERE category_id = 3 AND status = 'published'
ORDER BY created_at DESC, id DESC LIMIT 24 OFFSET 0;

SELECT * FROM designs WHERE title LIKE 'Modern%' ESCAPE '!'
ORDER BY created_at DESC, id DESC LIMIT 24 OFFSET 0;

SELECT * FROM designs ORDER BY created_at DESC, id DESC LIMIT 24 OFFSET 0;
```

| Listing case | Baseline access | Indexed chosen key | Indexed access / extra |
| --- | --- | --- | --- |
| newest_listing | ALL / none | idx_designs_created_id | index / Backward index scan |
| category_status_listing | ALL / none | idx_designs_category_status_created | ref / Backward index scan |
| category_only_listing | ALL / none | idx_designs_created_id | index / Using where; Backward index scan |
| status_only_listing | ALL / none | idx_designs_created_id | index / Using where; Backward index scan |
| english_prefix_listing | ALL / none | idx_designs_created_id | index / Using where; Backward index scan |
| arabic_prefix_listing | ALL / none | idx_designs_created_id | index / Using where; Backward index scan |
| combined_listing | ALL / none | idx_designs_category_status_created | ref / Using where; Backward index scan |
| rare_prefix_listing | ALL / none | idx_designs_title | range / Using index condition; Using filesort |
| absent_prefix_listing | ALL / none | idx_designs_title | range / Using index condition; Using filesort |
| deep_offset_listing | ALL / none | none | ALL / Using filesort |

### Interpretation and index decisions

- Default and category/status first pages benefit substantially from ordered index access rather than scanning/sorting the full dataset. The composite category/status/date/id index satisfies both equality filters and then the stable ordering, using backward scans on ASC keys.
- Category-only and common-prefix listings can choose the date/id index and filter as they scan. This works well for early pages in this synthetic distribution; it does not prove the same behavior for skewed or sparse real data.
- Common-prefix listing plans did not choose the title index, whereas rare and missing prefixes did. Prefix counts also use the title index. Thus title indexing is useful, but a title B-tree does not inherently satisfy date ordering and is not always the selected access path.
- The unfiltered COUNT was slower with the optimizer-selected secondary index in this run. An index is not a guarantee of faster counts; compare its actual EXPLAIN ANALYZE and repeat on the target environment before forcing a plan.
- Status-only COUNT had only a modest ratio; the category-first composite cannot seek efficiently by status alone. A (status, created_at, id) candidate needs a separate benchmark including write/storage costs and skew. It was not added automatically.
- Deep OFFSET did not improve: the optimizer used a table scan/filesort in both variants, and the indexed variant was slightly slower in this measurement. OFFSET still discards many rows. This is a limit of numbered deep pagination, not evidence that first-page ordering indexes are useless.
- No index should be removed or added solely from this small synthetic sample. There are only 10 categories, 8 base title patterns and roughly balanced statuses. Generalization to arbitrary titles, Arabic forms, status skew, concurrent users, and different hardware is limited.

## Playwright browser observations (separate experiment)

Used the Playwright MCP browser, logged in as Admin, selected Modern + Posters + Published and clicked Apply filters. It showed 833 matches and 24 rows. Page 2 retained all filters and displayed 25?48 of 833. No design data was changed.

Browser: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36. PHP artisan development server, local HTTP, authenticated session, default browser cache enabled, no throttling. Two warmups and ten sequential navigations for each URL. Navigation Timing values include framework/session/rendering and local browser work. They are **not** database index before/after measurements. The initial database run may have overlapped some browser observations; browser timings are illustrative, not isolated production benchmarks. The final SQL results above are from a separate rerun with Playwright idle.

| Page | Median TTFB | Median DOMContentLoaded | Median load event | P95 load event |
| --- | ---: | ---: | ---: | ---: |
| Default listing | 357.35 | 444.70 | 447.60 | 494.40 |
| Combined filters, page 2 | 340.70 | 414.30 | 416.60 | 490.60 |

Raw browser timings: [playwright-performance.json](playwright-performance.json). TTFB is requestStart?responseStart; DOM/load durations are relative to navigation start. Lazy images below the viewport need not load before the load event, so this does not measure loading all thumbnails or scrolling. Browser cache state is not equivalent to the MySQL buffer pool state.

To repeat browser timings, log in via Playwright MCP and run `scripts/benchmark-browser.js` with its code-file tool. Keep the browser session, cache settings and viewport consistent, and run it separately from the SQL benchmark.

## At one million designs

No million-row experiment was executed and no 10? latency prediction is made. Expected pressure points and follow-up work:

1. Re-run on a separate representative million-row dataset with skewed categories/statuses, diverse Arabic/English titles, concurrent readers/writers, fixed hardware and documented cache states.
2. COUNT and deep OFFSET are likely to dominate more often. Consider cursor/keyset pagination on (created_at,id) for next/previous navigation if product requirements permit; this changes numbered-page semantics and must define how nullable timestamps are handled. Consider cached/approximate counts only if stale counts are acceptable.
3. Benchmark category-only (category_id,created_at,id) and status-only (status,created_at,id) composites when those real queries warrant them. Measure writes, storage and buffer-pool residency before adding either index; preserve FK index coverage.
4. Size the buffer pool from measured active working set and available memory. Do not extrapolate current allocation statistics linearly as a capacity guarantee. Monitor EXPLAIN ANALYZE, examined rows, p95/p99, locks and slow-query logs under target concurrency.
5. Prefix search on a B-tree remains the chosen behavior. Leading-wildcard substring matching, stemming, typo tolerance and Arabic normalization need a separate search design; evaluate full-text or a search engine only if those product requirements arise.
6. Thumbnail delivery and page rendering require their own measurements; optimize image dimensions, lazy loading and caching independently from SQL.

## Sources

- [MySQL index hints](https://dev.mysql.com/doc/refman/8.4/en/index-hints.html): IGNORE INDEX excludes named access paths, retaining other available indexes.
- [MySQL EXPLAIN output](https://dev.mysql.com/doc/refman/8.4/en/explain-output.html): key is the selected index and rows is an estimate; actual execution details are retained separately.
