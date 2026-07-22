<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Unit;

use Drupal\Core\Plugin\Component;
use Drupal\sdc_devel\Component\ComponentValidatorSilencer;
use Drupal\Tests\Core\Theme\Component\ComponentValidatorTest;

/**
 * Simple test for ComponentValidator throw interception.
 *
 * This is a copy of ComponentValidatorTest.php without throw.
 *
 * @coversDefaultClass \Drupal\sdc_devel\Component\ComponentValidatorSilencer
 *
 * @group sdc_devel
 * @internal
 */
class ComponentValidatorSilencerTest extends ComponentValidatorTest {

  /**
   * Tests invalid component definitions intercept.
   *
   * @dataProvider dataProviderValidateDefinitionInvalid
   */
  public function testValidateDefinitionInvalid(array $definition): void {

    $component_validator = new ComponentValidatorSilencer();
    $component_validator->setValidator();
    $result = $component_validator->validateDefinition($definition, TRUE);
    $this->assertTrue($result);
  }

  /**
   * Tests that invalid props are intercepted.
   *
   * @todo fix signature with Drupal >11.1.1 and Twig > 3.19
   *
   * @dataProvider dataProviderValidatePropsInvalid
   */
  public function testValidatePropsInvalid(array $context, string $component_id, array $definition, string $expected_exception_message): void {
    $component = new Component(
      ['app_root' => '/fake/path/root'],
      'sdc_test:' . $component_id,
      $definition
    );
    $component_validator = new ComponentValidatorSilencer();
    $component_validator->setValidator();
    $result = $component_validator->validateProps($context, $component);
    $this->assertTrue($result);
  }

}
