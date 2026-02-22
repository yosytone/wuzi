<?php

namespace Drupal\wuzi_blocks\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Сервис для построения данных слайда из различных сущностей.
 */
class SlideBuilderService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a SlideBuilderService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Builds slide data array from an entity (Paragraph or Term).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity containing slide fields.
   * @param string $style_name
   *   Image style to apply. Defaults to 'wide'.
   *
   * @return array|null
   *   Render array for slide, or NULL if entity is not a valid slide.
   */
  public function buildSlideData(EntityInterface $entity, string $style_name = 'wide'): ?array {
    // Проверяем, что сущность имеет необходимые поля слайда.
    if (!$entity->hasField('field_slide_title')) {
      return NULL;
    }

    $image = $this->buildSlideImage($entity, $style_name);
    $link = $entity->get('field_slide_button_url')->first();

    $slide = [
      'image' => $image,
      'title' => $entity->get('field_slide_title')->value ?? '',
      'description' => $entity->hasField('field_slide_description')
        ? ($entity->get('field_slide_description')->value ?? '')
        : '',
      'button_url' => $link,
      'button_text' => $link ? ($link->title ?: 'Shop now') : '',
    ];

    // Добавляем данные категории, если это термин таксономии.
    if ($entity->getEntityTypeId() === 'taxonomy_term') {
      $slide['category_url'] = $entity->toUrl();
      $slide['category_name'] = $entity->getName();
      $slide['tid'] = $entity->id();
    }

    return $slide;
  }

  /**
   * Builds image render array for a slide.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity that may contain an image field.
   * @param string $style_name
   *   Image style name.
   *
   * @return array
   *   Render array for image, or empty array if no image.
   */
  /**
   * Builds image render array for a slide.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity that may contain an image field.
   * @param string $style_name
   *   Image style name.
   * @param string $field_name
   *   Field name that contains the image/media reference.
   *
   * @return array
   *   Render array for image, or empty array if no image.
   */
  public function buildSlideImage(
    EntityInterface $entity,
    string $style_name = 'wide',
    string $field_name = 'field_slide_image'
  ): array {
    $image = [];

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return $image;
    }

    $referenced_entity = $entity->get($field_name)->entity;
    if (!$referenced_entity) {
      return $image;
    }

    $uri = NULL;
    $alt = '';

    // Обработка Media-сущности.
    if ($referenced_entity instanceof MediaInterface && $referenced_entity->hasField('field_media_image')) {
      $media_image = $referenced_entity->get('field_media_image')->entity;
      if ($media_image instanceof FileInterface) {
        $uri = $media_image->getFileUri();
        $alt = $referenced_entity->get('field_media_image')->alt ?? '';
      }
    }
    // Прямая ссылка на файл.
    elseif ($referenced_entity instanceof FileInterface) {
      $uri = $referenced_entity->getFileUri();
      $alt = $entity->get($field_name)->alt ?? '';
    }

    if ($uri) {
      $image = [
        '#theme' => 'image_style',
        '#style_name' => $style_name,
        '#uri' => $uri,
        '#alt' => $alt,
      ];
    }

    return $image;
  }

  /**
   * Extracts slides from a paragraph reference field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity containing the paragraph reference field.
   * @param string $field_name
   *   Field name containing paragraph references.
   * @param string $paragraph_bundle
   *   Target paragraph bundle (e.g. 'slide').
   * @param string $style_name
   *   Image style to apply.
   *
   * @return array
   *   Array of slide data arrays.
   */
  public function extractSlidesFromParagraphs(
    EntityInterface $entity,
    string $field_name,
    string $paragraph_bundle,
    string $style_name = 'wide'
  ): array {
    $slides = [];

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return $slides;
    }

    foreach ($entity->get($field_name)->referencedEntities() as $paragraph) {
      if ($paragraph instanceof ParagraphInterface && $paragraph->bundle() === $paragraph_bundle) {
        $slide_data = $this->buildSlideData($paragraph, $style_name);
        if ($slide_data) {
          $slides[] = $slide_data;
        }
      }
    }

    return $slides;
  }

  

}