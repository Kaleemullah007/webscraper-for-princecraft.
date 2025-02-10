<?php
class ModelUrl
{
    private $base_url;
    private $outputFile;
    private $headers;
    private $lang_type;
    private $current_dir;
    public function __construct($base_url, $outputFile)
    {
        $this->base_url = rtrim($base_url, '/');
        $this->outputFile = $outputFile;
        $this->headers = [
            'Accept-Language: en-US,en;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'
        ];

        ini_set("max_execution_time", "-1");
        ini_set("memory_limit", "-1");
        ignore_user_abort(true);
        set_time_limit(0);
    }
    /**
     * Create Directory
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
        // $dir = $lang_type . '/tem';
        $dir = $lang_type . '/uploads';
        $dir =  'uploads' . '/' . $lang_type;
        if (!is_dir($dir)) {
            // Attempt to create the directory
            mkdir($dir, 0777, true);
        }
        $this->lang_type = $lang_type;
        $this->current_dir = $dir;
    }
    // Method to fetch URL content using cURL
    /**
     * Iniate a cURL session to fetch the webpage
     * @url string 
     * @headers array
     */
    public function fetchWebPage(string $url, array $headers = []): string
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
     * Method to parse and extract data using DOMDocument and DOMXPath
     * $htmlContent string
     */
    public function parseContent(string $htmlContent): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));

        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $products = [];
        $divs = $xpath->query('//article[@class="product-list-item"]');

        foreach ($divs as $div) {
            $temp = [];
            $imageData = [];

            // Extract images
            $images = $xpath->query('.//img', $div);
            foreach ($images as $img) {
                $srcset = $img->getAttribute('data-srcset');
                $alt = $img->getAttribute('alt');
                $imageData[] = [
                    'srcset' => $this->base_url . $srcset,
                    'alt' => $alt,
                ];
            }
            $temp['images'] = json_encode($imageData);

            // Extract heading
            $h2Elements = $xpath->query('.//h3', $div);
            if ($h2Elements->length > 0) {
                $temp['heading'] = trim($h2Elements[0]->textContent);
            }

            // Extract properties
            $ulElements = $xpath->query('.//ul', $div);
            $properties = [];
            foreach ($ulElements as $ul) {
                $liElements = $xpath->query('.//li', $ul);
                foreach ($liElements as $li) {
                    if ($li->textContent != '') {
                        $properties[] = trim($li->textContent);
                    }
                }
            }
            $temp['properties'] = json_encode($properties);

            // Extract links
            $aElements = $xpath->query('.//div[@class="product-list-txt"]//a', $div);
            $links = [];
            foreach ($aElements as $key => $a) {
                $href = $a->getAttribute('href');
                if ($key == 0) {
                    $href = $this->base_url . $href;

                    $model_products = $this->modelProducts($href);
                    $this->checkDir($href);
                    $this->saveDataToFile($model_products, $this->generateFileName('model-data-extract-products') . '.csv');
                }
                $links[] = $href;
            }
            $temp['model_link'] = json_encode($links);

            $products[] = $temp;
        }

        return $products;
    }

    /**
     * Method to save data to a CSV file
     * $data array
     *  */

     public function saveToCSV($data): void
    {

        $file = fopen($this->current_dir . '/' . $this->outputFile, 'w');
        if (!$file) {
            throw new Exception("Failed to open file: {$this->outputFile}");
        }

        // Write headers
        fputcsv($file, ['images', 'heading', 'properties', 'model_link']);

        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
    }


        /**
     * Save Main Url data
     * $data array
     * $fileName string
     */
    function saveDataToFile(array $data, $fileName): void
    {
        $file = fopen($this->current_dir . '/' . $fileName, 'w');

        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }

        fclose($file);
    }

    // Main method to scrape data from a list of URLs
    /**
     * Scrape the given URLs and save the data to a CSV file
     * $urls array
     */
    public function scrape(array $urls): void
    {
        $allData = [];

        foreach ($urls as $url) {
            echo "Scraping URL: $url\n";
            $this->checkDir($url);
            $content = $this->fetchWebPage($url);
            $data = $this->parseContent($content);
            $allData = array_merge($allData, $data);
        }

        $this->saveToCSV($allData);
        echo "Data successfully saved to {$this->current_dir}/{$this->outputFile}\n";
    }

        /**
     * Extract the data from the model products
     * $htmlContent string
     */
    function extractDataFromModel(string $htmlContent):array
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
                $images = $this->base_url . $img->getAttribute('data-src');

                $images =json_encode($images);
            }
            $figCaption = $figure->getElementsByTagName('figcaption')->item(0);
            $captionText = $figCaption ? $figCaption->textContent : '';
            $image['heading'] = $captionText;
            $image['image'] = $images;
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
        // $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue/Vogue-25-XT.aspx';
        $htmlContent = $this->fetchWebPage($url);
        return $this->extractDataFromModel($htmlContent);
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
}

// Usage
try {
    $scraper = new ModelUrl("https://www.princecraft.com/", "model-data-extract.csv");
    $urls = [
        "https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue.aspx"
    ];
    $scraper->scrape($urls);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
