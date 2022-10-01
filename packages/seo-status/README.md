# En cours et A venir

- [ ] Fix : local pack detector (ex: pied web)

- [ ] Entregistrer l'évolution des données SEO
      SeoGoogleData OneToMany SearchGoogleDataHistorical (per Week (cron to launch 1 time per week)
      -date
      -SearchOrganicCount / Paid / Total -

- [ ] When set Previous, add movement to SERP (pos/pixelPos) by updating for eachSearchResult
      -Move int unsigned (if new, set previous = lastSearchResultPos +1)
      -positive bool
      -New bool

  ```
  1. Parse each searchResult keeping only firstResult for a host (for previous and current) result[host] = SearchResult
  2. For each result[host] compare
    if exists previousResult[host]
      previousResult[host] - result[host] -> updateMovement
  ```

- [ ] Définir des tranches de pixel Pos ex: entre 0 et 200px = 1, entre 201 et 401 = 2, s'arrêter à 5 tranches (>5 tranches = dead) formuler en float : rank.pixelPos (simplifie la lecture et le trie)
- [ ] Import from GoogleSuggest Command

  - **importer depuis n'importe quel `Node`**
    curl getSearchToExtract to local command puis once/day import to parent
    => Ou un childNode peut être programmé pour envoyer ses derniers résultats (relation parent/enfant)
    **via SSH** + commande ou via rsync to data/dist + command search:import:from-dist? HTTP ?
    rsync -avz -e 'ssh' /path/to/local/dir user@remotehost:/path/to/remote/dir
    Lors de l'envoi, il supprime toutes les données qu'il détient
    Envoie sous forme de zip, merge avec le parent, puis import du parent
    => Un `node` demande à un autre `node` les résultats sur les X derniers jours en soumettant la liste des recherches déjà importer
    (à générer : search-lang-tld-ymd) - relation décentralisé
    => Ou un parentNode peut-être programmé pour récupérer régulièrement les résultats enfants (sic : not know IP)

- [ ] Volume and CPC

  - calculate visibility seo from pixelPosRank
    6: 0,001
    5: 0,05
    4: 0,1
    3: 0,15
    2: 0,3
    1: 0,399
  - retrieve volume from trends by downloading the last csv and calculate the average from last 12 months
  - CPC

- Visibility Indicator from positio
  How to work with database growing ? kw positionned / kw in database
- view KW
  Keyword - Volume
  SERP Features CPC
  ...
  LastExtractedAt - NextExtractionFrom - Extract Now (Action)
  ...
  Related / Similar / Comparable
  ...
  SERP Analysis (inspire by THRUUUU)
  ...
  DiffChecker (SERP Machine)
  Last / Previous https://vlad-cerisier.fr/wp-content/uploads/2021/04/serp-machine-assurance.png
  ...

- view KWS List

- [ ] Commande pour re-run le comparator

- [ ] Docs
- [ ] Plug SearchConsole API
- [ ] Compare performance for requesting all keywords for a domain
  - select DISTINCT search_results from search_result INNER JOIN ... where search_result.search_results.isLast = true and domain =

# Limit

With 1 kw checked every minuts

- bdd de 27millions de kw checké toutes les semaines => ~2700 proxies
- 4 millions => ~400proxies
- 400 proxies = `1 * Y (extract Time and Store) * 400 / 60 = X threads`
  => pricing = ressource serveur pour 1 check + proxyPrice/10080 + forfait maintenance et accès à la donnée

# Goal

- storing data a reusable way
- décentraliser avec le même source code qui se différencie par la programmation d'un cron

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
