<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Plugin\Component;
use Drupal\sdc_devel\Component\ComponentValidatorSilencer;
use Drupal\Tests\Core\Theme\Component\ComponentValidatorTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Simple test for ComponentValidator throw interception.
 *
 * @internal
 */
#[CoversClass(ComponentValidatorSilencer::class)]
#[Group('sdc_devel')]
final class ComponentValidatorSilencerTest extends ComponentValidatorTest {

  /**
   * Tests invalid component definitions intercept.
   */
  #[DataProvider('dataProviderValidateDefinitionInvalid')]
  public function testValidateDefinitionInvalid(array $definition): void {
    $component_validator = new ComponentValidatorSilencer();
    $component_validator->setValidator();
    $result = $component_validator->validateDefinition($definition, TRUE);
    self::assertTrue($result);
  }

  /**
   * Tests that invalid props are intercepted.
   *
   * @todo fix signature with Drupal >11.1.1 and Twig > 3.19
   */
  #[DataProvider('dataProviderValidatePropsInvalid')]
  public function testValidatePropsInvalid(array $context, string $component_id, array $definition, string $expected_exception_message): void {
    $translation = $this->getStringTranslationStub();
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    $component = new Component(
      ['app_root' => '/fake/path/root'],
      'sdc_test:' . $component_id,
      $definition
    );
    $component_validator = new ComponentValidatorSilencer();
    $component_validator->setValidator();
    $result = $component_validator->validateProps($context, $component);
    self::assertTrue($result);
  }

}
