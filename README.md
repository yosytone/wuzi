заменить код в modules/contrib/facets/modules/facets_range_widget/src/Plugin/facets/processor/SliderProcessor.php
````
public function postQuery(FacetInterface $facet) {
      $widget = $facet->getWidgetInstance();
      $config = $widget->getConfiguration();
      $step = (float) ($config['step'] ?: 1);
      $max_steps = 1000; // Максимально допустимое число шагов

      // Защита от некорректного шага
      if ($step <= 0) {
          $step = 1.0;
      }

      if ($config['min_type'] == 'fixed') {
          $min = (float) $config['min_value'];
          $max = (float) $config['max_value'];
      } else {
          // Определяем min и max из реальных результатов
          $real_min = null;
          $real_max = null;

          foreach ($facet->getResults() as $result) {
              $value = (float) $result->getRawValue();
              if ($real_min === null || $value < $real_min) {
                  $real_min = $value;
              }
              if ($real_max === null || $value > $real_max) {
                  $real_max = $value;
              }
          }

          if ($real_min === null || $real_max === null) {
              $facet->setResults([]);
              return;
          }

          $min = $real_min;
          $max = $real_max;

          // Расширяем max до кратного шагу (только если step разумный)
          $remainder = fmod($max - $min, $step);
          if ($remainder > 0) {
              $max += $step - $remainder;
          }
      }

      // Проверяем, не превышает ли количество шагов лимит
      $range = $max - $min;
      if ($range < 0) {
          $facet->setResults([]);
          return;
      }

      $estimated_steps = $range / $step;
      if ($estimated_steps > $max_steps) {
          // Автоматически увеличиваем шаг, чтобы уложиться в лимит
          $step = $range / $max_steps;
          // Округляем шаг "вверх" до удобочитаемого значения (опционально, но рекомендуется)
          // Простой способ: округлить до ближайшей "красивой" величины
          $step = $this->roundStep($step);
          // После изменения шага — пересчитываем max, чтобы он был кратен новому шагу
          $remainder = fmod($range, $step);
          if ($remainder > 0) {
              $max = $min + $step * ceil($range / $step);
          }
      }

      // Генерируем сетку
      $new_results = [];
      for ($value = $min; $value <= $max; $value += $step) {
          $rounded_value = round($value, 10);
          $new_results[] = new Result($facet, (float) $rounded_value, (float) $rounded_value, 0);
      }

      $facet->setResults($new_results);
  }

  /**
   * Вспомогательный метод: округляет шаг до "удобочитаемого" значения.
   * Например: 37 → 50, 123 → 200, 0.43 → 0.5, 0.017 → 0.02
   */
  protected function roundStep(float $step): float {
      if ($step <= 0) {
          return 1.0;
      }

      $log = floor(log10($step));
      $normalized = $step / pow(10, $log);

      // Округляем до ближайшего из [1, 2, 5, 10]
      if ($normalized <= 1.5) {
          $rounded_normalized = 1;
      } elseif ($normalized <= 3.5) {
          $rounded_normalized = 2;
      } elseif ($normalized <= 7.5) {
          $rounded_normalized = 5;
      } else {
          $rounded_normalized = 10;
      }

      return $rounded_normalized * pow(10, $log);
  }
````



<img alt="Drupal Logo" src="https://www.drupal.org/files/Wordmark_blue_RGB.png" height="60px">

Drupal is an open source content management platform supporting a variety of
websites ranging from personal weblogs to large community-driven websites. For
more information, visit the Drupal website, [Drupal.org][Drupal.org], and join
the [Drupal community][Drupal community].

## Contributing

Drupal is developed on [Drupal.org][Drupal.org], the home of the international
Drupal community since 2001!

[Drupal.org][Drupal.org] hosts Drupal's [GitLab repository][GitLab repository],
its [issue queue][issue queue], and its [documentation][documentation]. Before
you start working on code, be sure to search the [issue queue][issue queue] and
create an issue if your aren't able to find an existing issue.

Every issue on Drupal.org automatically creates a new community-accessible fork
that you can contribute to. Learn more about the code contribution process on
the [Issue forks & merge requests page][issue forks].

## Usage

For a brief introduction, see [USAGE.txt](/core/USAGE.txt). You can also find
guides, API references, and more by visiting Drupal's [documentation
page][documentation].

You can quickly extend Drupal's core feature set by installing any of its
[thousands of free and open source modules][modules]. With Drupal and its
module ecosystem, you can often build most or all of what your project needs
before writing a single line of code.

## Changelog

Drupal keeps detailed [change records][changelog]. You can search Drupal's
changes for a record of every notable breaking change and new feature since
2011.

## Security

For a list of security announcements, see the [Security advisories
page][Security advisories] (available as [an RSS feed][security RSS]). This
page also describes how to subscribe to these announcements via email.

For information about the Drupal security process, or to find out how to report
a potential security issue to the Drupal security team, see the [Security team
page][security team].

## Need a helping hand?

Visit the [Support page][support] or browse [over a thousand Drupal
providers][service providers] offering design, strategy, development, and
hosting services.

## Legal matters

Know your rights when using Drupal by reading Drupal core's
[license](/core/LICENSE.txt).

Learn about the [Drupal trademark and logo policy here][trademark].

[Drupal.org]: https://www.drupal.org
[Drupal community]: https://www.drupal.org/community
[GitLab repository]: https://git.drupalcode.org/project/drupal
[issue queue]: https://www.drupal.org/project/issues/drupal
[issue forks]: https://www.drupal.org/drupalorg/docs/gitlab-integration/issue-forks-merge-requests
[documentation]: https://www.drupal.org/documentation
[changelog]: https://www.drupal.org/list-changes/drupal
[modules]: https://www.drupal.org/project/project_module
[security advisories]: https://www.drupal.org/security
[security RSS]: https://www.drupal.org/security/rss.xml
[security team]: https://www.drupal.org/drupal-security-team
[service providers]: https://www.drupal.org/drupal-services
[support]: https://www.drupal.org/support
[trademark]: https://www.drupal.com/trademark
