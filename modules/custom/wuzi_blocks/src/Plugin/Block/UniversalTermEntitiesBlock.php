<?php

declare(strict_types=1);

namespace Drupal\wuzi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "universal_term_entities",
 *   admin_label = @Translation("Universal Term Entities"),
 * )
 */
class UniversalTermEntitiesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $routeMatch;

  public function __construct($configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

    public function build() {
      if ($this->routeMatch->getRouteName() !== 'entity.taxonomy_term.canonical') {
        return ['#markup' => ''];
      }

      $term = $this->routeMatch->getParameter('taxonomy_term');
      if (!$term instanceof TermInterface) {
        return ['#markup' => ''];
      }

      $tid = $term->id();
      $build = [];

      // ПОЛЯ НА ВАРИАЦИЯХ
      $variation_fields = [
        'field_product_material',
        'field_color',
        'field_product_colour',
        // другие поля вариаций
      ];

      // Находим вариации с нужным TID
      $variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
      $variation_query = $variation_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1);

      $or = $variation_query->orConditionGroup();
      foreach ($variation_fields as $field) {
  
        $field_defs = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('commerce_product_variation');
        if (isset($field_defs[$field]) && 
            $field_defs[$field]->getType() === 'entity_reference' &&
            $field_defs[$field]->getSetting('target_type') === 'taxonomy_term') {
          $or->condition($field, $tid);
        }
      }
      $variation_query->condition($or);
      $variation_query->range(0, 50);

      try {
        $variation_ids = $variation_query->execute();
        if (!empty($variation_ids)) {

          $variations = $variation_storage->loadMultiple($variation_ids);
          $product_ids = [];
          foreach ($variations as $variation) {
            if ($variation->hasField('product_id')) {
              $product_id = $variation->get('product_id')->target_id;
              if ($product_id) {
                $product_ids[] = $product_id;
              }
            }
          }

          $product_ids = array_unique($product_ids);

            if (empty($product_ids)) {
                return [
                '#theme' => 'universal_term_products',
                '#products' => [],
                '#term' => $term,
                ];
            }

            $product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
            $products = $product_storage->loadMultiple($product_ids);

            $published_products = array_filter($products, function ($product) {
                return $product->isPublished();
            });

            $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product');
              $rendered_products = [];
              foreach ($published_products as $product) {
                $rendered_products[] = $view_builder->view($product, 'list');
              }

              return [
                '#theme' => 'universal_term_products',
                '#rendered_products' => $rendered_products, 
                '#term' => $term,
              ];
        }
      } catch (\Exception $e) {
        \Drupal::logger('wuzi_blocks')->error('Variation query error: @message', ['@message' => $e->getMessage()]);
      }

      return $build ?: ['#markup' => ''];
    }
}