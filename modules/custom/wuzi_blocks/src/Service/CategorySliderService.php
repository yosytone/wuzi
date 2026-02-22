<?php

namespace Drupal\wuzi_blocks\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Сервис для загрузки и подготовки слайдов категорий.
 */
class CategorySliderService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The slide builder service.
   *
   * @var \Drupal\wuzi_blocks\Service\SlideBuilderService
   */
  protected SlideBuilderService $slideBuilder;

  /**
   * Constructs a CategorySliderService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SlideBuilderService $slide_builder
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->slideBuilder = $slide_builder;
  }

  /**
   * Loads root-level taxonomy terms for category slider.
   *
   * @param string $vocabulary
   *   Vocabulary machine name. Defaults to 'product_categories'.
   * @param int $limit
   *   Maximum number of terms to load. 0 = unlimited.
   *
   * @return array
   *   Array of category data with name, url, tid.
   */
public function getRootCategorySlides(string $vocabulary = 'product_categories', int $limit = 0): array {
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', $vocabulary)
      ->condition('status', 1)
      ->condition('parent', 0)
      ->accessCheck(TRUE)
      ->sort('weight', 'ASC')
      ->sort('name', 'ASC');

    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $tids = $query->execute();
    if (empty($tids)) {
      return [];
    }

    $categories = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);

    foreach ($terms as $term) {
      // Обрабатываем изображение из field_category_banner
      $image = $this->slideBuilder->buildSlideImage(
        $term,
        'wide', // Стиль изображения
        'field_category_banner' // Имя поля на термине
      );

      $categories[] = [
        'name' => $term->getName(),
        'url' => $term->toUrl(),
        'tid' => $term->id(),
        'image' => $image,
      ];
    }

    return $categories;
  }

  /**
   * Loads categories with their paragraph slides (if field exists).
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param string $paragraph_field
   *   Field name on term that holds paragraph slides.
   * @param string $paragraph_bundle
   *   Target paragraph bundle.
   * @param string $style_name
   *   Image style for slides.
   *
   * @return array
   *   Array of categories with nested slides.
   */
  public function getCategoriesWithSlides(
    string $vocabulary = 'product_categories',
    string $paragraph_field = 'field_category_slides',
    string $paragraph_bundle = 'slide',
    string $style_name = 'wide'
  ): array {
    $tids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', $vocabulary)
      ->condition('status', 1)
      ->condition('parent', 0)
      ->accessCheck(TRUE)
      ->sort('weight', 'ASC')
      ->sort('name', 'ASC')
      ->execute();

    if (empty($tids)) {
      return [];
    }

    $result = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);

    foreach ($terms as $term) {
      // Проверяем наличие поля с параграфами.
      if (!$term->hasField($paragraph_field) || $term->get($paragraph_field)->isEmpty()) {
        continue;
      }

      $slides = $this->slideBuilder->extractSlidesFromParagraphs(
        $term,
        $paragraph_field,
        $paragraph_bundle,
        $style_name
      );

      if (!empty($slides)) {
        $result[] = [
          'name' => $term->getName(),
          'url' => $term->toUrl(),
          'tid' => $term->id(),
          'items' => $slides,
        ];
      }
    }

    return $result;
  }

}