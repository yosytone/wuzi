const axios = require('axios');
const cheerio = require('cheerio');
const fs = require('fs').promises;
const path = require('path');

async function addTypeToProducts() {
  try {
    // Загружаем данные
    const productsPath = path.join(__dirname, 'products_detailed.json');
    const categoriesPath = path.join(__dirname, 'categories.json');

    const products = JSON.parse(await fs.readFile(productsPath, 'utf8'));
    const categories = JSON.parse(await fs.readFile(categoriesPath, 'utf8'));

    // Создаём маппинг: subcategoryName → parentCategory
    const subcategoryToParent = new Map();

    for (const cat of categories) {
      const parent = cat.category.trim();
      for (const sub of cat.subcategories) {
        const subName = sub.name.trim();
        subcategoryToParent.set(subName, parent);
      }
    }

    // Добавляем поле "type" к каждому товару
    let updatedCount = 0;
    for (const product of products) {
      const categoryName = product.category?.trim();
      if (categoryName) {
        const type = subcategoryToParent.get(categoryName) || categoryName; // fallback на саму категорию
        product.type = type;
        updatedCount++;
      } else {
        product.type = null;
      }
    }

    // Сохраняем обновлённый файл
    await fs.writeFile(productsPath, JSON.stringify(products, null, 2), 'utf8');

    console.log(`✅ Обновлено ${updatedCount} товаров.`);
    console.log(`📁 Файл сохранён: ${productsPath}`);
  } catch (err) {
    console.error('❌ Ошибка:', err.message);
  }
}

addTypeToProducts();