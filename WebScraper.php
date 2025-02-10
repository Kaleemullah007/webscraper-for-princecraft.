<?php

ini_set('max_execution_time', 600);

class WebScraper
{
    private $baseUrl;
    private $outputFile;
    private $lang_type;
    protected $current_dir;

    public function __construct($baseUrl, $outputFile)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->outputFile = $outputFile;
    }

    /**
     * Scrape the given URLs and save the data to a CSV file
     * $urls array
     */
    public function scrape(array $urls, string $page_type): void
    {
        $models = [];

        foreach ($urls as $url) {
            $this->checkDir($url);
            if (($page_type == $this->lang_type || $page_type == 'both')) {
                echo $url . "\n <br>";
                $htmlContent = $this->fetchWebPage($url);
                $models = array_merge($models, $this->extractData($htmlContent));
            }
        }

        $this->saveDataToFile($models, $this->outputFile);
    }

    /**
     * Create Directory
     * $dir string
     * $url string
     */
    public function checkDir($url): void
    {
        $lang_type = 'both';
        if (strpos($url, '/fr/') !== false) {
            $lang_type = 'fr';
        } elseif (strpos($url, '/en/') !== false) {
            $lang_type = 'en';
        }
        $dir = $lang_type . '/uploads';
        $dir =  'uploads' . '/' . $lang_type;
        if (!is_dir($dir)) {
            // Attempt to create the directory
            mkdir($dir, 0777, true);
        }
        $this->lang_type = $lang_type;
        $this->current_dir = $dir;
    }

    /**
     * Iniate a cURL session to fetch the webpage
     * @url string 
     * @headers array
     */
    function fetchWebPage(string $url, array $headers = []): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * Extracts the data from the HTML content
     * $htmlContent string
     */
    function extractData(string $htmlContent): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new DOMXPath($dom);
        $divs = $xpath->query('//figure[@class="row pb-xl-5 pb-4 align-items-center"]');
        $models = [];

        foreach ($divs as $div) {
            $model = [];

            $images = $this->extractImages($xpath, $div);
            $model['images'] = json_encode($images);

            $heading = $this->extractText($xpath, './/h3[contains(@class, "h2")]', $div);
            $model['heading'] = $heading;

            $subHeading = $this->extractText($xpath, './/h4[@class="h3 small"]', $div);
            $model['sub_heading'] = $subHeading;

            $properties = $this->extractListItems($xpath, $div);
            $model['properties'] = json_encode($properties);

            $modelLink = $this->extractLink($xpath, $div, './/a[@class="bt-action"]');
            if ($modelLink) {
                $model['model_link'] = $this->baseUrl . $modelLink;
                echo "\t \n" . $model['model_link'] . "\n";
                $this->scrapeModelDetails($heading, $model['model_link']);
            }

            $models[] = $model;
        }

        return $models;
    }

    /**
     * Extracts the Image from the HTML content
     * $xpath object
     * $contextNode object
     */
    function extractImages(object $xpath, object $contextNode): array
    {
        $images = [];
        $imageNodes = $xpath->query('.//img', $contextNode);

        foreach ($imageNodes as $img) {
            $images[] = [
                'srcset' => $this->baseUrl . $img->getAttribute('data-srcset'),
                'alt' => $img->getAttribute('alt'),
            ];
        }

        return $images;
    }

    /**
     * Extracts the Image from the HTML content
     * $xpath object
     * $contextNode object
     */
    function extractImageName(object $xpath, object $contextNode): string
    {


        $captionNode = $xpath->query('.//figcaption', $contextNode)->item(0);
        $captionText = $captionNode ? trim($captionNode->textContent) : '';


        return $captionText;
    }

    /**
     * Extracts the Text from the HTML content
     * $xpath object
     * $query string
     * $contextNode object 
     */
    function extractText(object $xpath, string $query, object $contextNode): string|null
    {
        $nodeList = $xpath->query($query, $contextNode);
        return $nodeList->length > 0 ? trim($nodeList[0]->textContent) : null;
    }

    /**
     * Extracts the text from the HTML content
     * $xpath object
     * $contextNode string  
     */
    function extractListItems(object $xpath, object $contextNode): array
    {
        $list = [];
        $ulNodes = $xpath->query('.//ul', $contextNode);

        foreach ($ulNodes as $ul) {
            $liNodes = $xpath->query('.//li', $ul);
            foreach ($liNodes as $li) {
                if (trim($li->textContent)) {
                    $list[] = trim($li->textContent);
                }
            }
        }

        return $list;
    }

    /**
     * Extracts the Text from the HTML content
     * $xpath object
     * $query string
     * $contextNode string 
     */
    function extractLink(object $xpath, object $contextNode, string $query): string|null
    {
        $nodeList = $xpath->query($query, $contextNode);
        return $nodeList->length > 0 ? $nodeList[0]->getAttribute('href') : null;
    }

    /**
     * $heading string
     * $modelLink string
     */
    function scrapeModelDetails($heading, $modelLink): void
    {
        $modelFileName = $this->generateFileName($heading) . '.csv';
        $htmlContent = $this->fetchWebPage($modelLink);
        $formData = $this->extractHiddenFields($htmlContent);
        $formData['CodePostal'] = 'J2H2T9';  // Your postal code (update as necessary)
        $formData['btnSend'] = 'Continuer';  // The button name or ID (update as needed)
        $postalCodeUrl = 'https://www.princecraft.com/content/Produits/CodePostal.aspx';
        $postResponse = $this->postPostalCode($postalCodeUrl, $formData);
        $productPageContent = $this->getPageContent($modelLink);
        $this->extractAndSaveModelDetails($productPageContent, $modelFileName);
    }

    /**
     * Collecting data of model page and write in the file
     * $htmlCOntent string 
     * $fileName string
     */
    function extractAndSaveModelDetails(string $htmlContent, string $fileName): array
    {
        echo "Extracting Model Product Images, Price, Features, Specification and Options <br>";
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new DOMXPath($dom);
        $divs = $xpath->query('//article[@class="product-list-item"]');
        $data = [];

        foreach ($divs as $div) {
            $model = [];

            $images = $this->extractImages($xpath, $div);
            $model['images'] = json_encode($images);

            $heading = $this->extractText($xpath, './/h3', $div);
            $model['heading'] = $heading;

            $properties = $this->extractListItems($xpath, $div);
            $model['properties'] = json_encode($properties);

            $links = $this->extrcctModelLink($xpath, $div, $heading);


            $features = isset($links['features']['features']) ? $links['features']['features'] : [];
            $specs = isset($links['features']['specs']) ? $links['features']['specs'] : [];
            $options = isset($links['features']['options']) ? $links['features']['options'] : [];

            $model['cash_price'] = $links['cash_price'];
            $model['starting_price'] = $links['starting_price'];
            $model['features'] = json_encode($features);
            $model['specification'] = json_encode($specs);
            $model['options'] = json_encode($options);
            $model['product_details'] = json_encode($links);

            // $this->modelProducts($heading);

            $data[] = $model;
        }

        echo "Saving this into this file $fileName <br>";
        $this->saveDataToFile($data, $fileName);
        return $data;
    }


    /**
     * Save Main Url data
     * $data array
     * $fileName string
     */
    function saveDataToFile(array $data, $fileName): void
    {
        if (!empty($data)) {
            $file = fopen($this->current_dir . '/' . $fileName, 'w');
            $headers = array_keys($data[0]);
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }
    }

    /**
     * Generate file name from the model name
     * $string string
     */
    function generateFileName(string $string): string
    {
        $fileName = preg_replace('/[^A-Za-z0-9-]/', '', $string);
        $fileName = str_replace(' ', '_', $fileName);
        return strtolower($fileName);
    }

    /**
     * Extract the link from the model page
     * $xpath object
     * $div object
     */
    function extrcctModelLink($xpath, $div, $heading): array
    {
        $aElements = $xpath->query('.//div[@class="product-list-txt"]//a', $div);
        // Extract product price
        $priceElement = $xpath->query('.//span[@class="product-list-price"]', $div);

        // print_r($priceElement);
        // die();
        $price = $priceElement->length > 0 ? trim($priceElement->item(0)->textContent) : 'Price not found';

        $priceElementRebate = $xpath->query('.//p[@class="mb-0"]//strong', $div); // Update XPath to find <strong> within <p class="mb-0">

        // Check if the price is found and extract the value
        $rprice = $priceElementRebate->length > 0 ? trim($priceElementRebate->item(0)->textContent) : 'Price not found';

        $links = [];
        foreach ($aElements as $key => $a) {
            $href = $a->getAttribute('href');
            if ($key == 0) {
                $href = $this->baseUrl . $href;
                $products = $this->modelProducts($href);
                $this->checkDir($href);
                $fileName = $this->generateFileName('products-' . $heading) . '.csv';
                echo "Saving data Of Product " . $heading . " into this file $fileName  <br>";
                $this->saveDataToFile($products, $fileName);
            }
            $links[] = $href;
        }
        $temp['model_link'] = $links;

        $features = $this->extractSpecifications($links[0]);
        $temp['starting_price'] = trim(str_replace('Starting at', '', $price)); // Add extracted price
        $temp['cash_price'] = $rprice; // Add extracted price
        $temp['features'] = $features;
        return $temp;
    }

    /**
     * Extract the data from the model products
     * $htmlContent string
     */
    function extractDataFromModel(string $htmlContent): array
    {

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        $figures = $xpath->query('//li[@class="col"]/figure');
        $products = [];
        foreach ($figures as $figure) {
            $imageNodes = $xpath->query('.//img', $figure);
            $images = '';
            foreach ($imageNodes as $img) {
                $images = $this->baseUrl . $img->getAttribute('data-src');

                $images = json_encode($images);
            }
            $figCaption = $figure->getElementsByTagName('figcaption')->item(0);
            $captionText = $figCaption ? $figCaption->textContent : '';
            $image['heading'] = $captionText;
            $image['image'] = $images;
            // $image['links'] = $links;

            $products[] = $image;
        }
        return $products;
    }

    /**
     * send Request to the model page product
     * $url string
     */
    function modelProducts($url = ''): array
    {
        $htmlContent = $this->fetchWebPage($url);
        return $this->extractDataFromModel($htmlContent);
    }

    /**
     * send Request to the model page Category
     * $url string
     */
    function ExtractCategories($url = ''): array
    {
        // $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue/Vogue-25-XT.aspx';
        $htmlContent = $this->fetchWebPage($url);
        $this->checkDir($url);
        $models = $this->extractDataFromModelCategories($htmlContent);


        $fileName = $this->generateFileName('Deck Boats') . '.csv';
        echo "Saving data  into this file $fileName  for Disk Boats <br>";
        $this->saveDataToFile($models, $fileName);
        return $models;
    }

    /**
     * Extract the data from the model products
     * $htmlContent string
     */
    function extractDataFromModelCategories(string $htmlContent): array
    {

        echo "Extracting Model <br>";
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        $figures = $xpath->query('//figure[@class="row pb-xl-5 pb-4 align-items-center"]');
        $products = [];

        foreach ($figures as $figure) {
            // Extract image
            $imageNode = $xpath->query('.//img', $figure)->item(0);
            $imageSrc = '';

            if ($imageNode) {
                $srcset = $imageNode->getAttribute('data-srcset');
                $imageUrls = explode(',', $srcset);
                if (!empty($imageUrls)) {
                    $imageSrc = trim(explode(' ', $imageUrls[0])[0]); // Get the first URL
                }
            }

            // Extract heading
            $headingNode = $xpath->query('.//figcaption/h3', $figure)->item(0);
            $headingText = $headingNode ? trim($headingNode->textContent) : '';
            $features = [];
            $listItems = $xpath->query('.//ul[@class="compact"]/li', $figure);
            foreach ($listItems as $li) {
                $features[] = trim($li->textContent);
            }

            // Extract link
            $linkNode = $xpath->query('.//figcaption//p/a', $figure)->item(0);
            $link = $linkNode ? $this->baseUrl . trim($linkNode->getAttribute('href')) : '';
            // Store extracted data
            $products[] = [
                'heading' => $headingText,
                'image' => $this->baseUrl . $imageSrc,
                'features' => json_encode($features),
                'link' => $link
            ];

            $model['model_link'] =  $link;
            echo "Extracting Model Details <br>";
            $this->scrapeModelDetails($headingText, $model['model_link']);
        }

        return $products;
    }


    /**
     * Getting specification, features and options of the product
     * $link string 
     */
    function extractSpecifications($link): array
    {

        $url = $link;
        $htmlContent = $this->fetchWebPage($url);
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        $specifications = [];
        $featureNodes = $xpath->query('//div[@id="features"]'); // Adjust XPath according to your page

        $specifications = array();
        $specifications = [];

        // Query to get the div with id="features"

        $divs = ['features', 'specs', 'options'];
        foreach ($divs as $div) {
            $featureNodes = $xpath->query('//div[@id="' . $div . '"]');

            // Loop through each featureNode (if there are multiple)
            foreach ($featureNodes as $node) {
                // Get the accordion items within the "features" div
                $accordions = $xpath->query('.//div[@class="accordion-item"]', $node);

                // Initialize counter for button-text mapping
                $counter = 0;

                // Loop through each accordion
                foreach ($accordions as $accordion) {
                    // Get the button text within the accordion
                    $buttonNode = $xpath->query('.//button', $accordion);
                    $buttonText = ($buttonNode->length > 0) ? trim($buttonNode->item(0)->nodeValue) : 'No Button';

                    // Get the list items (<li>) inside the accordion body
                    $liItems = $xpath->query('.//div[contains(@class,"accordion-body")]//ul//li', $accordion);

                    // Loop through the list items and store them with the button text as the key
                    foreach ($liItems as $li) {
                        $specifications[$div][$buttonText][] = trim($li->nodeValue);
                    }

                    // Increment counter (optional, if you plan to do something with it)
                    $counter++;
                }
            }
        }
        return $specifications;
    }

    /**
     * Product page and accept cookie contsent
     * $url string
     */
    function getPageContent($url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Set cookies (including cookie consent) - ensure cookies are set properly
        curl_setopt($ch, CURLOPT_COOKIE, 'cookieConsent=accepted;'); // Adjust cookie consent if needed

        // Set user agent and necessary headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
        ]);

        // Use a temporary cookie jar to store cookies during the session
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');  // Save cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt'); // Load cookies

        // Execute the request
        $response = curl_exec($ch);

        // Check for any errors
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }

    /**
     *  Function to extract hidden fields from the form (e.g., VIEWSTATE, EVENTVALIDATION) 
     * $html string
     * */
    function extractHiddenFields($html): array
    {
        preg_match('/name="__VIEWSTATE" value="(.*?)"/', $html, $viewState);
        preg_match('/name="__EVENTVALIDATION" value="(.*?)"/', $html, $eventValidation);
        preg_match('/name="__VIEWSTATEGENERATOR" value="(.*?)"/', $html, $viewStateGenerator);

        return [
            '__VIEWSTATE' => $viewState[1] ?? '',
            '__EVENTVALIDATION' => $eventValidation[1] ?? '',
            '__VIEWSTATEGENERATOR' => $viewStateGenerator[1] ?? ''
        ];
    }

    /** 
     * Function to simulate the POST request for the postal code and btn name
     * */
    function postPostalCode($url, $formData): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Set cookies for POST request (use cookies.txt to persist cookies across requests)
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');  // Save cookies in cookies.txt
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt'); // Load cookies for the session

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
        ]);

        // Execute the POST request
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * Function to extract prices from the page content 
     * */
    function extractPrices($html): array
    {
        // Check the HTML content to confirm that prices are present
        preg_match_all('/<span class="product-list-price">(.*?)<\/span>/', $html, $matches);

        // Return cleaned prices (remove HTML tags)
        return array_map('strip_tags', $matches[1]); // Clean the price data
    }
}

// Usage
$baseUrl = "https://www.princecraft.com/";
$urls = [
    'https://www.princecraft.com/ca/en/products/Fishing-Boats.aspx',
    'https://www.princecraft.com/ca/en/products/Pontoon-Boats.aspx'
];
$outputFile =  'Ponton-Boats.csv';
$name = 'en';
if (php_sapi_name() === 'cli' || PHP_SAPI === 'cli') {
    echo "Which Page do want to Scrap, Default both pages \n ";
    $name = readline(); // Waits for user input from the terminal
    echo "\n" . $name;
}

echo "start time  " . date('Y-m-d H:i:s') . "<br>";
$scraper = new WebScraper($baseUrl, $outputFile);

// $scraper->modelProducts();
// die();
$scraper->scrape($urls, strtolower($name));
$url = 'https://www.princecraft.com/ca/en/products/Deck-Boats.aspx';
$data = $scraper->ExtractCategories($url, strtolower($name));

// $data = $scraper->extractSpecifications();

echo "<br>Done";
echo "<br>End time  " . date('Y-m-d H:i:s');
