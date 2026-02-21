const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs').promises;
const path = require('path');

async function parseCategories(url) {
  try {
    const { data } = await axios.get(url.trim(), {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
      }
    });

    const $ = cheerio.load(data);
    const result = [];

    // Проходим по каждому блоку категории
    $('.catalog-category-item-list-wrap').each((_, block) => {
      const $block = $(block);

      // Название категории
      const categoryName = $block.find('.product-name').first().text().trim();

      if (!categoryName) return; // пропускаем, если нет имени

      const subcategories = [];

      // Ищем все подкатегории внутри .catalog-category-item-wrap
      $block.find('.catalog-category-item-wrap a').each((__, link) => {
        const $link = $(link);
        const href = $link.attr('href');
        const fullText = $link.text().trim();
        const name = fullText.replace(/\s*\(\d+\)\s*$/, '').trim();

        if (href && name) {
          // ❌ Пропускаем, если имя подкатегории совпадает с именем категории
          if (name === categoryName) {
            return; // skip
          }
          subcategories.push({
            name,
            url: new URL(href, url).href
          });
        }
      });

      result.push({
        category: categoryName,
        subcategories
      });
    });

    return result;
  } catch (error) {
    console.error('❌ Ошибка при парсинге:', error.message);
    return [];
  }
}

// === Основная функция ===
(async () => {
  const baseUrl = 'https://upakmarket.com/e-store/xml_catalog/';
  console.log('🔍 Парсинг категорий...');

  const categories = await parseCategories(baseUrl);

  // Вывод в консоль
  categories.forEach(cat => {
    console.log(`\n📦 ${cat.category}`);
    cat.subcategories.forEach(sub => {
      console.log(`  └── ${sub.name} → ${sub.url}`);
    });
  });

  // Сохранение в JSON
  const outputPath = path.join(__dirname, 'categories.json');
  await fs.writeFile(outputPath, JSON.stringify(categories, null, 2), 'utf8');

  console.log(`\n✅ Сохранено ${categories.length} категорий в ${outputPath}`);
})();