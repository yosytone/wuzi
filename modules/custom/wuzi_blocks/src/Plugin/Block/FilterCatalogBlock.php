<?php

declare(strict_types=1);

namespace Drupal\wuzi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a filter_catalog block.
 *
 * @Block(
 *   id = "wuzi_blocks_filter_catalog",
 *   admin_label = @Translation("Filter Catalog"),
 *   category = @Translation("Custom"),
 * )
 */
final class FilterCatalogBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    return [
      '#theme' => 'wuzi_blocks_filter_catalog',
    ];
  }

}