<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../WebScraper.php'; // Adjust path if needed
class WebScraperTest extends TestCase
{
    private $webScraper;
    
    protected function setUp(): void
    {
        $this->webScraper = new WebScraper("https://www.princecraft.com/", "testfile.csv");
    }

    public function testFetchWebPage()
    {
        $url = "https://www.princecraft.com/ca/en/products/Pontoon-Boats.aspx";
        $htmlContent = $this->webScraper->fetchWebPage($url);
        $this->assertNotEmpty($htmlContent, "Failed to fetch web page content.");
    }
    
    /**
     * Extract Model and it's properties.
     */

    public function testExtractModelProperties()
    {
        $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue.aspx';
        $htmlContent = $this->webScraper->fetchWebPage($url);
        $this->webScraper->checkDir($url);
        $modelFileName = $this->webScraper->generateFileName('testfile') . '.csv';
        $properties = $this->webScraper->extractAndSaveModelDetails($htmlContent,$modelFileName);
        $this->assertEquals(3, count($properties), "Failed to extract Models.");
        $pro = json_decode($properties[0]['properties'],true);
        $this->assertEquals(4, count($pro), "Failed to extract Model properties.");
        $this->assertEquals(4, count($pro));
        $this->assertEquals("Longueur : 8.5 m (27’-11”)", $pro[0], "Incorrect Properties  text.");
    }

    
    /**
     * Test to extract model products
     */
    public function testExtractModelProducts()
    {
        $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue/Vogue-25-XT.aspx';
        $total_products = $this->webScraper->modelProducts($url);
        $this->assertEquals(10,count($total_products));
    }

     

}
