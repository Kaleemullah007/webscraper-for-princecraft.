<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../ModelUrl.php'; // Adjust path if needed
class ModelUrlTest extends TestCase
{
    private $webScraper;
    
    protected function setUp(): void
    {
        $this->webScraper = new ModelUrl("https://www.princecraft.com/", "testfile.csv");
    }

   
 /**
     * Extract Model and it's properties.
     */

     public function testExtractModelPropertiesSpecific()
     {
         $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue.aspx';
         $htmlContent = $this->webScraper->fetchWebPage($url);
         $this->webScraper->checkDir($url);
         $properties = $this->webScraper->parseContent($htmlContent);
         $this->assertEquals(3, count($properties), "Failed to extract Models.");
         $pro = json_decode($properties[0]['properties'],true);
         $this->assertEquals(4, count($pro), "Failed to extract Model properties.");
         $this->assertEquals(4, count($pro));
         $this->assertEquals("Longueur : 8.5 m (27’-11”)", $pro[0], "Incorrect Properties  text.");
     }
 
     
     /**
      * Test to extract model products
      */
     public function testExtractModelProductsSpecific()
     {
         $url = 'https://www.princecraft.com/ca/fr/produits/Pontons/2025/Serie-Vogue/Vogue-25-XT.aspx';
         $total_products = $this->webScraper->modelProducts($url);
         $this->assertEquals(10,count($total_products));
     }

}
