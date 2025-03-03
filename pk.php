<?php

// Path to store cookies
$cookieFile = __DIR__ . '/cookies.txt';

// Unified cURL handler with cookie persistence
function curlRequest($url, $postData = null, $headers = []) {
    global $cookieFile;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        ], $headers),
    ]);

    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);

    return $response;
}

// Function to extract hidden form fields (VIEWSTATE, EVENTVALIDATION, etc.)
function extractFormFields($html): array {
    $fields = [];
    preg_match('/name="__VIEWSTATE" value="(.*?)"/', $html, $matches) && $fields['__VIEWSTATE'] = $matches[1];
    preg_match('/name="__EVENTVALIDATION" value="(.*?)"/', $html, $matches) && $fields['__EVENTVALIDATION'] = $matches[1];
    preg_match('/name="__VIEWSTATEGENERATOR" value="(.*?)"/', $html, $matches) && $fields['__VIEWSTATEGENERATOR'] = $matches[1];
    preg_match('/name="__EVENTTARGET" value="(.*?)"/', $html, $matches) && $fields['__EVENTTARGET'] = $matches[1];
    preg_match('/name="__EVENTARGUMENT" value="(.*?)"/', $html, $matches) && $fields['__EVENTARGUMENT'] = $matches[1];
    preg_match('/name="btnSend" value="(.*?)"/', $html, $matches) && $fields['btnSend'] = $matches[1];
    return $fields;
}

// Function to simulate the POST request for the postal code
function postPostalCode($url, $formData): string {
    global $cookieFile;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Requested-With: XMLHttpRequest',
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Step 1: Initial request to load the product page and accept cookies
$productUrl = 'https://www.princecraft.com/ca/en/products/Fishing-Boats/2025/Platinum-Series.aspx';
$response = curlRequest($productUrl);

// Step 2: Extract the dynamic form fields from the page
$formFields = extractFormFields($response);

// Step 3: Prepare the postal code data and POST request
$postalData = $formFields + [
    'CodePostal' => 'J2H2T9',  // Static postal code
    'btnSend' => 'Continue'     // Static button value
];

// Step 4: Submit postal code form (POST request)
$postalUrl = 'https://www.princecraft.com/content/Produits/CodePostal.aspx';
$postalResponse = postPostalCode($postalUrl, $postalData);

// Step 5: Reload the product page after submitting postal code to get final prices
$finalResponse = curlRequest($productUrl);

// Price extraction function
function extractPrices($html): array {
    // Regex to extract the prices from the page (modify as necessary based on the actual HTML structure)
    preg_match_all('/<span class="product-list-price">(.*?)<\/span>/', $html, $matches);
    return array_map('strip_tags', $matches[1]); // Clean the price data
}

// Step 6: Extract and display the prices
$prices = extractPrices($finalResponse);
echo "Extracted Prices:\n";
print_r($prices);

?>
