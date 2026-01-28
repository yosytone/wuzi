<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\taxonomy\Entity\Term;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;

$autoloader = require_once 'autoload.php';
$kernel = new DrupalKernel('prod', $autoloader);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

$vocabulary = 'product_categories';
// Словари для ссылочных полей
$vocabularies = [
  'Материал' => 'material',
  'Цвет' => 'colour',
];

function delete_all_products() {
  // Удаляем все продукты

  echo "Удаление всех товаров...\n";
  $products = \Drupal::entityTypeManager()
    ->getStorage('commerce_product')
    ->loadMultiple();

  $count = 0;
  $len = count($products);
  foreach ($products as $product) {
    $product->delete();
    $count++;
    echo "$len / $count товаров.\n";
  }

  echo "Удалено $count товаров.\n";
}


function delete_all_terms($voc) {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $voc]);

  $count = 0;
  $len = count($terms);
  foreach ($terms as $term) {
    $term->delete();
    $count++;
    echo "$len / $count товаров.\n";
  }

  echo "Удалено $count терминов из словаря '$voc'.\n";
}

function getOrCreateTerm($vocabulary, $name, $parent_id = 0) {
  // Ищем термин по имени и словарю (родитель не обязателен при поиске)
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties([
      'vid' => $vocabulary,
      'name' => $name,
    ]);

  if (!empty($terms)) {
    return reset($terms);
  }

  // Создаём новый термин
  $term_data = [
    'vid' => $vocabulary,
    'name' => $name,
  ];

  // Добавляем parent только если он задан и не 0
  if ($parent_id > 0) {
    $term_data['parent'] = $parent_id;
  }

  $term = Term::create($term_data);
  $term->save();
  return $term;
}


function loadJSON($filename='/products_detailed.json') {
  // --- Загрузка JSON ---
  $jsonPath = __DIR__ . '/products_detailed.json';
  if (!file_exists($jsonPath)) {
    die("❌ Файл не найден: $jsonPath\n");
  }
  $productsData = json_decode(file_get_contents($jsonPath), true);
  if (!is_array($productsData)) {
    die("❌ Неверный формат JSON\n");
  }
  return $productsData;
}

function parseDimension($value) {
  $value = trim($value);

  if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(см|м)?$/iu', $value, $matches)) {
    $number = (float) str_replace(',', '.', $matches[1]);
    $unit = strtolower(trim($matches[2] ?? ''));

    if ($unit === 'м') {
      return (int) round($number * 10); // как вы просили: 27 м → 270
    } else {
      return (int) round($number);
    }
  }

  return 0;
}

//delete_all_terms($vocabulary);
//delete_all_products();


$productsData = loadJSON();
$total = count($productsData);

$fieldMap = [
  'Штук в упаковке' => 'field_units',
  'Код товара' => 'field_code',
  'Материал' => 'field_product_material',
  'Цвет' => 'field_product_colour',
  'Длина' => 'field_length',
  'Ширина' => 'field_width',
  'Диаметр' => 'field_diameter',
  'Размер' => 'field_size',
];

foreach ($productsData as $index => $item) {
  //if ($index > 3) break;

  echo "[" . ($index + 1) . "/$total] Обработка: " . $item['title'] . "\n";

  $level1 = getOrCreateTerm($vocabulary, $item['type']);
  $level2 = getOrCreateTerm($vocabulary, $item['category'], $level1->id());
  $level3 = getOrCreateTerm($vocabulary, $item['subcategory'], $level2->id());

  $charsMap = [];
  foreach ($item['chars'] ?? [] as $char) {
    $label = $char['field'] ?? '';
    $value = $char['value'] ?? '';
    $charsMap[$label] = $value;
  }

  if (isset($charsMap['Длина'])) {
    $charsMap['Длина'] = parseDimension($charsMap['Длина']);
  }
  if (isset($charsMap['Ширина'])) {
    $charsMap['Ширина'] = parseDimension($charsMap['Ширина']);
  }

  $variationData = [
    'type' => 'default',
    'sku' => 'SKU-' . md5($item['title'] . $item['price']) . '-' . time(),
    'title' => $item['title'],
    'price' => [
      'number' => $item['price'],
      'currency_code' => 'RUB',
    ],
    'field_upakmarket_image_url' => $item['image'] ?? '',
    'field_artikul' => $item['chars']['Код товара'] ?? '',
  ];

  foreach ($fieldMap as $label => $fieldName) {
    if (!isset($charsMap[$label])) continue;

    $value = trim($charsMap[$label]);

    // Пропускаем, если значение пустое, "0", "Нет" и т.п.
    if ($value === '' || $value === '0' || strtolower($value) === 'нет') {
      continue;
    }

    if (isset($vocabularies[$label])) {
      $term = getOrCreateTerm($vocabularies[$label], $value);
      if ($term) {
        $variationData[$fieldName] = $term->id();
      }
    } elseif (in_array($fieldName, ['field_length', 'field_width', 'field_units', 'field_diameter'])) {
      // Числовые поля → преобразуем в int, только если число
      if (is_numeric($value)) {
        $num = (int) $value;
        if ($num !== 0) { 
          $variationData[$fieldName] = $num;
        }
      }
    } else {
      if ($value !== '') {
        $variationData[$fieldName] = $value;
      }
    }
  }

  $variation = ProductVariation::create($variationData);
  $variation->save();

  $product = Product::create([
    'type' => 'default',
    'title' => $item['title'],
    'variations' => [$variation->id()],
    'field_prod_category' => $level3->id(),
    'body' => $item['description'] ?? '',
  ]);

  $product->save();

  var_dump($charsMap);
}

