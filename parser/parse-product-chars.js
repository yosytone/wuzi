const fs = require('fs').promises;
const path = require('path');
const axios = require('axios');
const cheerio = require('cheerio');

// Вспомогательная функция: парсит "Штук в упаковке: 1000" → { field: "Штук в упаковке", value: "1000" }
function parseCharacteristic(text) {
  // Разделяем по первому двоеточию
  const colonIndex = text.indexOf(':');
  if (colonIndex === -1) {
    return { field: text.trim(), value: '' };
  }
  const field = text.substring(0, colonIndex).trim();
  const value = text.substring(colonIndex + 1).trim();
  return { field, value };
}

async function parseProductData(productUrl) {
  try {
    const cleanUrl = productUrl.trim();
    const { data } = await axios.get(cleanUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
      }
    });

    const $ = cheerio.load(data);

    // === Описание ===
    let description = '';
    $('div.product-tab').each((_, elem) => {
      const $elem = $(elem);
      if ($elem.attr('tab_name') === 'description') {
        description = $elem.text().trim();
        return false;
      }
    });

    // === Цена ===
    let price = '';
    const priceEl = $('span[itemprop="price"]').first();
    if (priceEl.length) {
      price = priceEl.text().trim();
    }

    // === Характеристики ===
    let characteristicsTab = null;
    $('div.product-tab').each((_, elem) => {
      const $elem = $(elem);
      if ($elem.attr('tab_name') === 'characteristics') {
        characteristicsTab = $elem;
        return false;
      }
    });

    const characteristics = [];
    if (characteristicsTab) {
      characteristicsTab.find('div.catalog-product-chars').each((_, elem) => {
        const text = $(elem).text().trim();
        if (text) {
          characteristics.push(parseCharacteristic(text));
        }
      });
    }

    // === Изображение ===
    let imgurl = null;
    const bigImageHref = $('div.product-item-detail-slider-image.active a').first().attr('href');
    if (bigImageHref) {
      imgurl = new URL(bigImageHref, cleanUrl).href;
    }

    return { description, price, characteristics, imgurl };
  } catch (error) {
    console.error(`❌ Ошибка при парсинге ${productUrl.trim()}:`, error.message);
    return { description: '', price: '', characteristics: [], imgurl: null };
  }
}

async function main() {
  const LIMIT = 0; // измените по желанию
  try {
    const jsonPath = path.join(__dirname, 'catalog_final.json');
    const catalog = JSON.parse(await fs.readFile(jsonPath, 'utf8'));

    const result = [];
    let totalProducts = 0;

    outerCategoryLoop:
    for (const category of catalog) {
      for (const filter of category.filters) {
        for (const product of filter.products) {
          if (LIMIT > 0 && totalProducts >= LIMIT) break outerCategoryLoop;

          console.log(`Обработка: ${product.title} [${totalProducts + 1}/${LIMIT}]`);

          const { description, price, characteristics, imgurl } = await parseProductData(product.href);

          result.push({
            title: product.title,
            description: description,
            image: imgurl,
            price: price,
            category: category.category,
            subcategory: filter.label,
            chars: characteristics
          });

          totalProducts++;
        }
      }
    }

    // Сохраняем в JSON
    const outputPath = path.join(__dirname, 'products_detailed.json');
    await fs.writeFile(outputPath, JSON.stringify(result, null, 2), 'utf8');

    console.log(`\n✅ Сохранено ${result.length} товаров в ${outputPath}`);
  } catch (err) {
    console.error('Ошибка:', err.message);
  }
}

main();