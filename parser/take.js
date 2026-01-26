const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs').promises;
const path = require('path');

// Парсинг товаров по URL (без фильтров)
async function parseProducts(url) {
  try {
    const cleanUrl = url.trim();
    const { data } = await axios.get(cleanUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
      }
    });
    const $ = cheerio.load(data);

    const products = [];
    $('.product-item-wrap').each((_, elem) => {
      const link = $(elem).find('a.catalog-item-img');
      const title = link.attr('title');
      const href = link.attr('href');

      if (title && href) {
        products.push({
          title: title.trim(),
          href: new URL(href, cleanUrl).href,
        });
      }
    });

    return products;
  } catch (error) {
    console.error(`❌ Ошибка при парсинге товаров с ${url}:`, error.message);
    return [];
  }
}

// Извлечение фильтров FILTER[PROP][243][] со страницы категории
// Извлечение фильтров FILTER[PROP][243][] со страницы категории (без дублей)
async function extractFiltersFromCategory(categoryUrl) {
  try {
    const cleanUrl = categoryUrl.trim();
    const { data } = await axios.get(cleanUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
      }
    });
    const $ = cheerio.load(data);

    const seen = new Set();
    const filters = [];

    $('div.filter-param-item').each((_, elem) => {
      const input = $(elem).find('input[name="FILTER[PROP][243][]"]');
      if (input.length) {
        const value = input.attr('value');
        const label = input.siblings('label').text().trim();
        if (value && !seen.has(value)) {
          seen.add(value);
          filters.push({ value, label });
        }
      }
    });

    return filters;
  } catch (error) {
    console.error(`⚠️ Не удалось извлечь фильтры с ${categoryUrl}:`, error.message);
    return [];
  }
}

// Основная функция
(async () => {
  const baseUrl = 'https://upakmarket.com/e-store/xml_catalog/';
  const { data } = await axios.get(baseUrl.trim(), {
    headers: {
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    }
  });

  const $ = cheerio.load(data);
  const categories = [];

  // 1. Собираем категории
  $('li.subcatalog-item').each((_, elem) => {
    const link = $(elem).find('a');
    const href = link.attr('href');
    const text = link.text().trim();
    if (href) {
      categories.push({
        category: text,
        url: new URL(href, baseUrl).href
      });
    }
  });

  console.log(`📦 Найдено категорий: ${categories.length}`);

  // 2. Для каждой категории — получаем фильтры и парсим товары по каждому фильтру
  const result = [];

  for (const cat of categories) {
    console.log(`\n📂 Обработка категории: ${cat.category}`);
    const filters = await extractFiltersFromCategory(cat.url);

    if (filters.length === 0) {
      console.log(`   ⚠️  Фильтры не найдены`);
      // Можно добавить парсинг без фильтра, если нужно
      continue;
    }

    const enrichedFilters = [];

    for (const filter of filters) {
      console.log(`   🔍 Фильтр: ${filter.label} (${filter.value})`);

      // Формируем URL с фильтром
      const filterUrl = `${cat.url}?FILTER[PROP][243][]=${encodeURIComponent(filter.value)}&paginator_offset=all`; //https://upakmarket.com/e-store/xml_catalog/pakety_tipa_mayka/?FILTER[PROP][243][]=6197&paginator_offset=all

      const products = await parseProducts(filterUrl);
      enrichedFilters.push({
        value: filter.value,
        label: filter.label,
        productCount: products.length,
        products
      });
    }

    result.push({
      category: cat.category,
      url: cat.url,
      filters: enrichedFilters
    });
  }

  // 3. Сохраняем результат
  const outputPath = path.join(__dirname, 'catalog_final.json');
  await fs.writeFile(outputPath, JSON.stringify(result, null, 2), 'utf8');

  console.log(`\n✅ Результат сохранён в ${outputPath}`);
  console.log(`📊 Всего категорий: ${result.length}`);
})();