<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

require dirname(__DIR__).'/vendor/autoload.php';

class Scrape
{
    private const BASE_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';
    private const PAGE_SELECTOR = '#pages a';

    private HttpClientInterface $client;
    private array $products = [];

    public function __construct()
    {
        $this->client = HttpClient::create();
    }

    private function fetchPage(int $page = 1): string
    {
        $response = $this->client->request('GET', self::BASE_URL."?page={$page}");
        return $response->getContent();
    }

    private function getAvailablePages(Crawler $crawler): array
    {
        $pages = $crawler->filter(self::PAGE_SELECTOR)
            ->each(fn (Crawler $node) => (int) $node->text());

        $pages = array_unique($pages);
        sort($pages);

        return $pages;
    }

    private function scrapeProductsFromPage(string $html): array
    {
        $crawler = new Crawler($html);
        $products = [];

        $crawler->filter('#products .product')->each(function (Crawler $node) use (&$products) {
            //fetch all colors for the product
            $colorVariants = $node->filter('span[data-colour]')
                ->each(fn (Crawler $colorNode) => $colorNode->attr('data-colour'));

            foreach ($colorVariants as $color) {
                $product = Product::fromRawData(
                    title: $node->filter('.product-name')->text(),
                    price: $node->filter('.my-8.block.text-center.text-lg')->text(),
                    imageUrl: $node->filter('img')->attr('src') ?? '',
                    capacity: $node->filter('.product-capacity')->text(),
                    colour: $color,
                    availabilityText: trim(str_replace(
                        'Availability:',
                        '',
                        $node->filter('.product .bg-white div:nth-child(5)')->text()
                    )),
                    shippingText: $node->filter('.my-4.text-sm.block.text-center')->eq(1)->text('')
                );

                //check for duplicates before adding
                if (! $this->isDuplicate($product)) {
                    $products[] = $product;
                }
            }
        });

        return $products;
    }

    private function isDuplicate(Product $newProduct): bool
    {
        foreach ($this->products as $existingProduct) {
            if (
                strtolower($existingProduct->title) === strtolower($newProduct->title) &&
                $existingProduct->capacityMB === $newProduct->capacityMB &&
                strtolower($existingProduct->colour) === strtolower($newProduct->colour)
            ) {
                return true;
            }
        }
        return false;
    }

    private function scrapeAllProducts(): void
    {
        $firstPageHtml = $this->fetchPage(1);
        $crawler = new Crawler($firstPageHtml);
        $availablePages = $this->getAvailablePages($crawler);

        //process first page
        $this->products = $this->scrapeProductsFromPage($firstPageHtml);

        foreach ($availablePages as $page) {
            try {
                $html = $this->fetchPage($page);
                $pageProducts = $this->scrapeProductsFromPage($html);
                $this->products = [...$this->products, ...$pageProducts];
            } catch (\Exception $e) {
                echo "Failed to process page {$page}: {$e->getMessage()}\n";
            }
        }
    }

    public function run(): void
    {
        $this->scrapeAllProducts();

        $uniqueProducts = array_map(
            fn (Product $product) => $product->toArray(),
            array_values($this->products)
        );

        //sort products by variant for consistent display
        usort($uniqueProducts, function ($a, $b) {
            return strcmp($a['title'].$a['colour'].$a['capacityMB'],
                $b['title'].$b['colour'].$b['capacityMB']);
        });

        file_put_contents(
            __DIR__.'/../output.json',
            json_encode(
                $uniqueProducts,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        echo "Scraping complete! Check output.json for results.\n";
    }
}


$scrape = new Scrape();
$scrape->run();
