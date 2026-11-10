<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Events;

use Drupal\Component\EventDispatcher\Event;

/**
 * Implementation of Entity Usage events.
 */
class EntityUsageEvent extends Event {

  /**
   * EntityUsageEvents constructor.
   *
   * @param int|string|null $targetEntityId
   *   The target entity ID.
   * @param string|null $targetEntityType
   *   The target entity type.
   * @param int|string|null $sourceEntityId
   *   The source entity ID.
   * @param string|null $sourceEntityType
   *   The source entity type.
   * @param string|null $sourceEntityLangcode
   *   The source entity language code.
   * @param int|null $sourceEntityRevisionId
   *   The source entity revision ID.
   * @param string|null $method
   *   The method or way the two entities are being referenced.
   * @param string|null $fieldName
   *   The name of the field in the source entity using the target entity.
   * @param int|null $count
   *   The number of references to add or remove.
   */
  public function __construct(
    protected int|string|null $targetEntityId = NULL,
    protected ?string $targetEntityType = NULL,
    protected int|string|null $sourceEntityId = NULL,
    protected ?string $sourceEntityType = NULL,
    protected ?string $sourceEntityLangcode = NULL,
    protected ?int $sourceEntityRevisionId = NULL,
    protected ?string $method = NULL,
    protected ?string $fieldName = NULL,
    protected ?int $count = NULL,
  ) {
  }

  /**
   * Sets the target entity id.
   *
   * @param int|string $id
   *   The target entity id.
   */
  public function setTargetEntityId(int|string $id): void {
    $this->targetEntityId = $id;
  }

  /**
   * Sets the target entity type.
   *
   * @param string $type
   *   The target entity type.
   */
  public function setTargetEntityType(string $type): void {
    $this->targetEntityType = $type;
  }

  /**
   * Sets the source entity id.
   *
   * @param int|string $id
   *   The source entity id.
   */
  public function setSourceEntityId(string|int $id): void {
    $this->sourceEntityId = $id;
  }

  /**
   * Sets the source entity type.
   *
   * @param string $type
   *   The source entity type.
   */
  public function setSourceEntityType(string $type): void {
    $this->sourceEntityType = $type;
  }

  /**
   * Sets the source entity language code.
   *
   * @param string $langcode
   *   The source entity language code.
   */
  public function setSourceEntityLangcode(string $langcode): void {
    $this->sourceEntityLangcode = $langcode;
  }

  /**
   * Sets the source entity revision ID.
   *
   * @param int $vid
   *   The source entity revision ID.
   */
  public function setSourceEntityRevisionId(int $vid): void {
    $this->sourceEntityRevisionId = $vid;
  }

  /**
   * Sets the method used to relate source entity with the target entity.
   *
   * @param string $method
   *   The source method.
   */
  public function setMethod(string $method): void {
    $this->method = $method;
  }

  /**
   * Sets the field name.
   *
   * @param string $field_name
   *   The field name.
   */
  public function setFieldName(string $field_name): void {
    $this->fieldName = $field_name;
  }

  /**
   * Sets the count.
   *
   * @param int $count
   *   The number od references to add or remove.
   */
  public function setCount(int $count): void {
    $this->count = $count;
  }

  /**
   * Gets the target entity id.
   *
   * @return int|string|null
   *   The target entity id or NULL.
   */
  public function getTargetEntityId(): int|string|null {
    return $this->targetEntityId;
  }

  /**
   * Gets the target entity type.
   *
   * @return null|string
   *   The target entity type or NULL.
   */
  public function getTargetEntityType(): ?string {
    return $this->targetEntityType;
  }

  /**
   * Gets the source entity id.
   *
   * @return int|string|null
   *   The source entity id or NULL.
   */
  public function getSourceEntityId(): int|string|null {
    return $this->sourceEntityId;
  }

  /**
   * Gets the source entity type.
   *
   * @return null|string
   *   The source entity type or NULL.
   */
  public function getSourceEntityType(): ?string {
    return $this->sourceEntityType;
  }

  /**
   * Gets the source entity language code.
   *
   * @return null|string
   *   The source entity language code or NULL.
   */
  public function getSourceEntityLangcode(): ?string {
    return $this->sourceEntityLangcode;
  }

  /**
   * Gets the source entity revision ID.
   *
   * @return int|string|null
   *   The source entity revision ID or NULL.
   */
  public function getSourceEntityRevisionId(): int|string|null {
    return $this->sourceEntityRevisionId;
  }

  /**
   * Gets the method used to relate source entity with the target entity.
   *
   * @return null|string
   *   The method or NULL.
   */
  public function getMethod(): ?string {
    return $this->method;
  }

  /**
   * Gets the field name.
   *
   * @return null|string
   *   The field name or NULL.
   */
  public function getFieldName(): ?string {
    return $this->fieldName;
  }

  /**
   * Gets the count.
   *
   * @return null|int
   *   The number of references to add or remove or NULL.
   */
  public function getCount(): ?int {
    return $this->count;
  }

}
