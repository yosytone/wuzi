<?php

declare(strict_types=1);

namespace Drupal\wuzi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\taxonomy\TermInterface;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a 'SimilarProductsBlock' block.
 *
 * @Block(
 *   id = "similar_products_block",
 *   admin_label = @Translation("Similar Products by Category"),
 *   category = @Translation("Commerce")
 * )
 */
class SimilarProductsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new SimilarProductsBlock object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#markup' => $this->t('No similar products found.'),
      '#cache' => [
        'contexts' => ['url'],
        'tags' => [],
      ],
    ];

    // Проверяем, что мы на странице товара Commerce.
    if ($this->routeMatch->getRouteName() !== 'entity.commerce_product.canonical') {
      return $build;
    }

    $product = $this->routeMatch->getParameter('commerce_product');
    if (!$product || $product->isNew()) {
      return $build;
    }

    // Получаем категорию товара.
    if (!$product->hasField('field_prod_category') || $product->get('field_prod_category')->isEmpty()) {
      return $build;
    }

    $terms = $product->get('field_prod_category')->referencedEntities();
    if (empty($terms)) {
      return $build;
    }

    // Берём первый термин (можно расширить для нескольких).
    $term = reset($terms);
    $tid = $term->id();

    // Запускаем View.
    $view = Views::getView('similar_prod'); // ← замените на ваше машинное имя View
    if (!$view) {
      return $build;
    }

    $display_id = 'block_1'; // ← замените на ID вашего блочного дисплея
    if (!$view->setDisplay($display_id)) {
      return $build;
    }

    // Передаём tid как аргумент.
    $view->setArguments([$tid]);
    $view->execute();

    // Исключаем текущий товар из результатов (опционально, если не сделано в View).
    $result = [];
    foreach ($view->result as $index => $row) {
      if (isset($row->_entity) && $row->_entity->id() != $product->id()) {
        $result[] = $row;
      }
    }
    $view->result = $result;

    if (empty($view->result)) {
      return $build;
    }

    $build = $view->render();
    // Добавляем кэш-теги текущего товара и термина.
    $build['#cache']['tags'] = array_merge(
      $build['#cache']['tags'] ?? [],
      $product->getCacheTags(),
      $term->getCacheTags()
    );
    $build['#cache']['contexts'][] = 'url';

    return $build;
  }

}