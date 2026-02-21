<?php

declare(strict_types=1);

namespace Drupal\wuzi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a catalog tree block.
 *
 * @Block(
 *   id = "wuzi_blocks_catalogtree",
 *   admin_label = @Translation("Catalog Tree"),
 *   category = @Translation("Custom"),
 * )
 */
final class CatalogtreeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $vocab_machine_name = 'product_categories'; // замените на ваш словарь

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vocab_machine_name, 0, NULL, TRUE);

    if (empty($terms)) {
      return ['#markup' => $this->t('No categories found.')];
    }

    $nested_tree = $this->buildNestedTree($terms);

    return [
      '#theme' => 'wuzi_blocks_catalog_tree',
      '#tree' => $nested_tree,
      '#cache' => [
        'tags' => ['taxonomy_term_list:' . $vocab_machine_name],
        'contexts' => ['url.path'],
      ],
    ];
  }

  /**
   * Builds a nested array from a flat taxonomy tree.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms
   *   Flat list of terms with parent info.
   * @param int $parent_id
   *   Parent term ID to build children for.
   *
   * @return array
   *   Nested tree structure.
   */
  protected function buildNestedTree(array $terms, int $parent_id = 0): array {
    $result = [];

    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      if ((int) $term->parent->target_id === $parent_id) {
        $children = $this->buildNestedTree($terms, (int) $term->id());
        $result[] = [
          'term' => $term,
          'children' => $children,
        ];
      }
    }

    return $result;
  }

}