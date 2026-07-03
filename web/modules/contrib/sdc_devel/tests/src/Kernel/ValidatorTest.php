<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\KernelTests\Components\ComponentKernelTestBase;
use Drupal\sdc_devel\DefinitionValidator;
use Drupal\sdc_devel\TwigValidator\TwigValidator;
use Drupal\sdc_devel\Validator;

/**
 * @coversDefaultClass \Drupal\sdc_devel\Validator
 *
 * @group sdc_devel
 * @internal
 *
 * phpcs:disable Drupal.Commenting.VariableComment.Missing
 */
class ValidatorTest extends ComponentKernelTestBase {

  protected static $modules = [
    'system',
    'sdc_devel',
  ];

  protected static $themes = ['sdc_devel_theme_test'];

  protected TwigValidator $twigValidator;

  protected DefinitionValidator $definitionValidator;

  protected Validator $validator;

  protected ComponentPluginManager $componentPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');

    $this->componentPluginManager = $this->container->get('plugin.manager.sdc');

    $this->twigValidator = $this->container->get('sdc_devel.twig_validator');
    $this->definitionValidator = $this->container->get('sdc_devel.definition_validator');

    $this->validator = new Validator(
      $this->twigValidator,
      $this->definitionValidator
    );
  }

  /**
   * @covers ::validate
   * @covers ::validateComponent
   * @covers ::checkEnumDefault
   * @covers ::validatePropsFromStories
   * @covers ::checkEmptyArrayObject
   * @covers \Drupal\sdc_devel\DefinitionValidator::validateComponent
   * @covers \Drupal\sdc_devel\TwigValidator\TwigValidator::validateComponent
   */
  public function testValidate(): void {
    $component_id = 'sdc_devel_theme_test:foo';
    $component = $this->componentPluginManager->find($component_id);

    $this->validator->validate($component_id, $component);
    $errors = $this->validator->getMessages();

    $this->assertEmpty($errors, \sprintf('Found errors in the component: %s', $component_id));
  }

  /**
   * @covers ::validate
   * @covers ::validateComponent
   * @covers ::checkEnumDefault
   * @covers ::validatePropsFromStories
   * @covers ::checkEmptyArrayObject
   * @covers \Drupal\sdc_devel\DefinitionValidator::validateComponent
   * @covers \Drupal\sdc_devel\TwigValidator\TwigValidator::validateComponent
   */
  public function testValidateError(): void {
    $component_id = 'sdc_devel_theme_test:error';
    $component = $this->componentPluginManager->find($component_id);

    $this->validator->validate($component_id, $component);
    $errors = $this->validator->getMessages();

    $expected = [
      // Twig errors.
      'Unused variables: attributes, test_slot_string, nothing, object, array, array_object, test_prop_string_error, test_enum_string, test_meta_enum_items_string',
      'Filter `trans` or `t` unsafe translation, do not translate variables!',
      "Don't use `default` filter on boolean.",
      // Schema errors.
      'A single variant do not need to be declared.',
      'Required slots are not recommended.',
      'Slots should not have type, perhaps this should be a prop.',
      'Default value must be in the enum.',
      'All key-value pairs in meta:enum string are identical.',
      'Empty object.',
      'Empty array.',
      'Array of empty object.',
    ];

    foreach ($errors as $key => $error) {
      $this->assertSame($expected[$key], (string) $error->message(), sprintf('Message %s do not match.', $key));
    }
  }

  /**
   * @covers ::validate
   * @covers ::validateComponent
   * @covers ::checkEnumDefault
   * @covers ::validatePropsFromStories
   * @covers ::checkEmptyArrayObject
   * @covers \Drupal\sdc_devel\DefinitionValidator::validateComponent
   * @covers \Drupal\sdc_devel\TwigValidator\TwigValidator::validateComponent
   */
  public function testValidateFail(): void {
    $component_id = 'sdc_devel_theme_test:fail';
    $component = $this->componentPluginManager->find($component_id);

    $this->validator->validate($component_id, $component);
    $errors = $this->validator->getMessages();

    // @todo fix duplicate.
    $expected = [
      // 'An exception has been thrown during the rendering of a template',
      'Unexpected "}".',
      'Unexpected "}".',
    ];

    $this->assertCount(\count($expected), $errors);

    foreach ($errors as $key => $error) {
      $this->assertSame($expected[$key], (string) $error->message(), sprintf('Message %s do not match.', $key));
    }
  }

}
