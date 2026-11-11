<?php

declare(strict_types=1);

namespace Drupal\scheduled_transitions\Plugin\Menu\LocalTask;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a local task showing count of scheduled transitions for an entity.
 *
 * @phpstan-type Definition array{base_route: string}&array<mixed>
 */
class ScheduledTransitionsLocalTask extends LocalTaskDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  final public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    TranslationInterface $stringTranslation,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $stringTranslation;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(RouteMatchInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(LanguageManagerInterface::class),
      $container->get(TranslationInterface::class),
    );
  }

  // @phpstan-ignore-next-line method.childReturnType
  public function getTitle(?Request $request = NULL): MarkupInterface|string {
    $entity = $this->getEntityFromRouteMatch();
    if ($entity === NULL) {
      return '';
    }
    $count = $this->getEntityTransitionCount($entity);
    // Drupal hints only `string` is allowed. It is wrong.
    return $this->t('@title (@count)', [
      '@title' => parent::getTitle($request),
      '@count' => $count,
    ]);
  }

  public function getCacheTags(): array {
    $tags = parent::getCacheTags();

    $entity = $this->getEntityFromRouteMatch();
    if ($entity !== NULL) {
      $tags[] = ScheduledTransitionsUtility::createScheduledTransitionsCacheTag($entity);
    }
    return $tags;
  }

  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    return $contexts;
  }

  /**
   * Get entity from route match.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity from route match.
   */
  protected function getEntityFromRouteMatch(): ?ContentEntityInterface {
    // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
    [1 => $entityTypeId] = \explode('.', $this->pluginDefinition['base_route']);

    // Get the first parameter in the route definition matching the entity type,
    // since the upcasted entity parameter could be something like {entity}.
    $parameters = $this->routeMatch->getParameters()->all();
    foreach ($parameters as $parameter) {
      if ($parameter instanceof ContentEntityInterface && $parameter->getEntityTypeId() === $entityTypeId) {
        return $parameter;
      }
    }

    return NULL;
  }

  /**
   * Gets the number of the transitions for an entity.
   *
   * @return int
   *   The number of transitions for an entity.
   */
  protected function getEntityTransitionCount(ContentEntityInterface $entity): int {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    return $transitionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->condition('entity_revision_langcode', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId())
      ->condition('is_processed', 0)
      ->count()
      ->execute();
  }

}
