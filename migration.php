<?php

// из папки web: fin exec php migration.php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Queue\QueueFactory;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;


///////////////////////////////////////////////////

$autoloader = require_once 'autoload.php';
$kernel = new DrupalKernel('prod', $autoloader);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

///////////////////////////////////////////////////
$variation = ProductVariation::create([
  'type' => 'default', // machine name типа вариации
  'sku' => 'PROD-001-RED',
  'title' => 'Товар — Красный',
  'price' => [
    'number' => '199.99',
    'currency_code' => 'RUB',
  ],
  // Дополнительные поля, если есть:
  // 'field_color' => 'red',
]);

$variation->save();
$variation_id = $variation->id();

echo "variation_id: $variation_id\n";


$product = Product::create([
  'type' => 'default', // machine name типа товара
  'title' => 'Мой товар',
  'variations' => [$variation_id],
  // Если есть другие поля:
  // 'field_brand' => '...',
]);

$product->save();
$product_id = $product->id();   

echo "product_id: $product_id\n";