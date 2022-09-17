# En cours et A venir

- [ ] searchGoogleData.searchResultsHash
  Run search comparator on first extraction

- [ ] Url / domain / uri
- [ ] Return to Datetime
- [ ] Commande pour ré-importer dans la db
- [ ] Programmer un cron
- [ ] Ajouter quelques données
- [ ] Docs
- [ ] Plug SearchConsole API
- [ ] Compare performance for requesting all keywords for a domain
  - select DISTINCT search_results from search_result INNER JOIN ... where search_result.search_results.isLast = true and domain =

# Goal

- storing data a reusable way

- SEARCH PROJECT MANAGER :
  Easy to Manage Web Interface inspire from my spreadsheet

- Website Observer : Find all Kw where domain is :
  Load search result 1/1 and keep id if domain found

- Keyword Analyser
  Summarize all Goodness data for a kw
  - last result URL data (harvest)
  - AI Generated Perfect content (plus with OpenAi)

* Compare similare KW
  Load a KW last search result
  compare it one per one to other search result
  if contain 7/10 same result => same kw

* URL Analyzer

- Website Crawler (inspire by ScreamingFrom)

## Flat File JSON DATABASE

- SEARCH

  - keyword
  - ...
  - last_search_result
  - before_last_search_result
  - total_search_result
  - first_result_date
  - last_result_date

- SEARCH_RESULTS
  (id) id_search_result // (id_search)\_YmdHis
  (object) organic {url, pos, pixelPos, title}
  (object) paid {url, pos, pixelPos, title}
  (list) serpFeature
  (list) related string[]

- SEARCH_PROJECT
  id_search_project uniqid
  (string) name
  (list) searches [search, expected, note]

- CRAWL_PROJECT
  id_crawl_project uniqid
  (string) name
  (string) pages, ex : All internal (url start with %regex%) page accessible from [url1, url2, url3] with max-depth (6, internal)

- URL
  (id_url) domain_sha1 ex: altimood.com+{(int) protocol}+sha1
  (string) url
  (datetime) crawledAt
  (object) metaData !toDigg
  (string) source
  (list) identique
  (list) duplicate
  (list) archive [datetime]
  On new, rename current to

        // /** @var array<string, mixed> */
        // $googleDataToImport = \Safe\json_decode($json, true)['searchGoogleData'] ?? []; // @phpstan-ignore-line
        // $googleData = $search->getSearchGoogleData();
        // foreach ($googleDataToImport as $key => $value) {
        //     if ('search' === $key) {
        //         continue;
        //     }
        //     $setter = 'set'.ucfirst($key);
        //     if (method_exists($googleData, $setter)) {
        //         $googleData->$setter($value);
        //     }
        // }
