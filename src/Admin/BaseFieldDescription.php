<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Admin;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Sonata\AdminBundle\Exception\NoValueException;

/**
 * A FieldDescription hold the information about a field. A typical
 * admin instance contains different collections of fields.
 *
 * - form: used by the form
 * - list: used by the list
 * - filter: used by the list filter
 *
 * Some options are global across the different contexts, other are
 * context specifics.
 *
 * Global options :
 *   - type (m): define the field type (use to tweak the form or the list)
 *   - template (o) : the template used to render the field
 *   - name (o) : the name used (label in the form, title in the list)
 *   - link_parameters (o) : add link parameter to the related Admin class when
 *                           the Admin.generateUrl is called
 *   - code : the method name to retrieve the related value
 *   - associated_tostring : (deprecated, use associated_property option)
 *                           the method to retrieve the "string" representation
 *                           of the collection element.
 *   - associated_property : property path to retrieve the "string" representation
 *                           of the collection element.
 *
 * Form Field options :
 *   - field_type (o): the widget class to use to render the field
 *   - field_options (o): the options to give to the widget
 *   - edit (o) : list|inline|standard (only used for associated admin)
 *      - list : open a popup where the user can search, filter and click on one field
 *               to select one item
 *      - inline : the associated form admin is embedded into the current form
 *      - standard : the associated admin is created through a popup
 *
 * List Field options :
 *   - identifier (o): if set to true a link appear on to edit the element
 *
 * Filter Field options :
 *   - options (o): options given to the Filter object
 *   - field_type (o): the widget class to use to render the field
 *   - field_options (o): the options to give to the widget
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
abstract class BaseFieldDescription implements FieldDescriptionInterface
{
    /**
     * @var string the field name
     */
    protected $name;

    /**
     * @var string|int the type
     */
    protected $type;

    /**
     * @var string|int the original mapping type
     */
    protected $mappingType;

    /**
     * @var string the field name (of the form)
     */
    protected $fieldName;

    /**
     * @var array the ORM association mapping
     */
    protected $associationMapping = [];

    /**
     * @var array the ORM field information
     */
    protected $fieldMapping = [];

    /**
     * @var array the ORM parent mapping association
     */
    protected $parentAssociationMappings = [];

    /**
     * @var string the template name
     */
    protected $template;

    /**
     * @var array the option collection
     */
    protected $options = [];

    /**
     * @var AdminInterface|null the parent Admin instance
     */
    protected $parent;

    /**
     * @var AdminInterface|null the related admin instance
     */
    protected $admin;

    /**
     * @var AdminInterface|null the associated admin class if the object is associated to another entity
     */
    protected $associationAdmin;

    /**
     * @var string the help message to display
     */
    protected $help;

    /**
     * @var array[] cached object field getters
     */
    private static $fieldGetters = [];

    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }

    public function getFieldName()
    {
        return $this->fieldName;
    }

    public function setName($name)
    {
        $this->name = $name;

        if (!$this->getFieldName()) {
            $this->setFieldName(substr(strrchr('.'.$name, '.'), 1));
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    public function setOptions(array $options)
    {
        // set the type if provided
        if (isset($options['type'])) {
            $this->setType($options['type']);
            unset($options['type']);
        }

        // remove property value
        if (isset($options['template'])) {
            $this->setTemplate($options['template']);
            unset($options['template']);
        }

        // NEXT_MAJOR: Remove this block.
        // set help if provided
        if (isset($options['help'])) {
            @trigger_error(sprintf(
                'Passing "help" option to "%s()" is deprecated since sonata-project/admin-bundle 3.74'
                .' and the option will be removed in 4.0.'
                .' Use Symfony Form "help" option instead.',
                __METHOD__
            ), E_USER_DEPRECATED);

            $this->setHelp($options['help'], 'sonata_deprecation_mute');
            unset($options['help']);
        }

        // set default placeholder
        if (!isset($options['placeholder'])) {
            $options['placeholder'] = 'short_object_description_placeholder';
        }

        if (!isset($options['link_parameters'])) {
            $options['link_parameters'] = [];
        }

        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        if (null !== $this->template && !\is_string($this->template) && 'sonata_deprecation_mute' !== (\func_get_args()[0] ?? null)) {
            @trigger_error(sprintf(
                'Returning other type than string or null in method %s() is deprecated since'
                .' sonata-project/admin-bundle 3.65. It will return only those types in version 4.0.',
                __METHOD__
            ), E_USER_DEPRECATED);
        }

        return $this->template;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setParent(AdminInterface $parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        if (!$this->hasParent()) {
            @trigger_error(
                sprintf(
                    'Calling %s() when there is no parent is deprecated since sonata-project/admin-bundle 3.69'
                    .' and will throw an exception in 4.0. Use %s::hasParent() to know if there is a parent.',
                    __METHOD__,
                    __CLASS__
                ),
                E_USER_DEPRECATED
            );
            // NEXT_MAJOR : remove the previous `trigger_error()` call, uncomment the following exception and declare AdminInterface as return type
            // throw new \LogicException(sprintf('%s has no parent.', static::class));
        }

        return $this->parent;
    }

    public function hasParent()
    {
        return null !== $this->parent;
    }

    public function getAssociationMapping()
    {
        return $this->associationMapping;
    }

    public function getFieldMapping()
    {
        return $this->fieldMapping;
    }

    public function getParentAssociationMappings()
    {
        return $this->parentAssociationMappings;
    }

    public function setAssociationAdmin(AdminInterface $associationAdmin)
    {
        $this->associationAdmin = $associationAdmin;
        $this->associationAdmin->setParentFieldDescription($this);
    }

    public function getAssociationAdmin()
    {
        if (!$this->hasAssociationAdmin()) {
            @trigger_error(
                sprintf(
                    'Calling %s() when there is no association admin is deprecated since'
                    .' sonata-project/admin-bundle 3.69 and will throw an exception in 4.0.'
                    .' Use %s::hasAssociationAdmin() to know if there is an association admin.',
                    __METHOD__,
                    __CLASS__
                ),
                E_USER_DEPRECATED
            );
            // NEXT_MAJOR : remove the previous `trigger_error()` call, uncomment the following exception and declare AdminInterface as return type
            // throw new \LogicException(sprintf('%s has no association admin.', static::class));
        }

        return $this->associationAdmin;
    }

    public function hasAssociationAdmin()
    {
        return null !== $this->associationAdmin;
    }

    public function getFieldValue($object, $fieldName)
    {
        if ($this->isVirtual() || null === $object) {
            return null;
        }

        $getters = [];
        $parameters = [];

        // prefer method name given in the code option
        if ($this->getOption('code')) {
            $getters[] = $this->getOption('code');
        }
        // parameters for the method given in the code option
        if ($this->getOption('parameters')) {
            $parameters = $this->getOption('parameters');
        }

        if (\is_string($fieldName) && '' !== $fieldName) {
            if ($this->hasCachedFieldGetter($object, $fieldName)) {
                return $this->callCachedGetter($object, $fieldName, $parameters);
            }

            $camelizedFieldName = InflectorFactory::create()->build()->classify($fieldName);

            $getters[] = sprintf('get%s', $camelizedFieldName);
            $getters[] = sprintf('is%s', $camelizedFieldName);
            $getters[] = sprintf('has%s', $camelizedFieldName);
        }

        foreach ($getters as $getter) {
            if (method_exists($object, $getter) && \is_callable([$object, $getter])) {
                $this->cacheFieldGetter($object, $fieldName, 'getter', $getter);

                return $object->{$getter}(...$parameters);
            }
        }

        if (method_exists($object, '__call')) {
            $this->cacheFieldGetter($object, $fieldName, 'call');

            return $object->{$fieldName}(...$parameters);
        }

        if (isset($object->{$fieldName})) {
            $this->cacheFieldGetter($object, $fieldName, 'var');

            return $object->{$fieldName};
        }

        throw new NoValueException(sprintf(
            'Neither the property "%s" nor one of the methods "%s()" exist and have public access in class "%s".',
            $this->getName(),
            implode('()", "', $getters),
            \get_class($object)
        ));
    }

    public function setAdmin(AdminInterface $admin)
    {
        $this->admin = $admin;
    }

    public function getAdmin()
    {
        if (!$this->hasAdmin()) {
            @trigger_error(
                sprintf(
                    'Calling %s() when there is no admin is deprecated since sonata-project/admin-bundle 3.69'
                    .' and will throw an exception in 4.0. Use %s::hasAdmin() to know if there is an admin.',
                    __METHOD__,
                    __CLASS__
                ),
                E_USER_DEPRECATED
            );
            // NEXT_MAJOR : remove the previous `trigger_error()` call, uncomment the following exception and declare AdminInterface as return type
            // throw new \LogicException(sprintf('%s has no admin.', static::class));
        }

        return $this->admin;
    }

    public function hasAdmin()
    {
        return null !== $this->admin;
    }

    public function mergeOption($name, array $options = [])
    {
        if (!isset($this->options[$name])) {
            $this->options[$name] = [];
        }

        if (!\is_array($this->options[$name])) {
            throw new \RuntimeException(sprintf('The key `%s` does not point to an array value', $name));
        }

        $this->options[$name] = array_merge($this->options[$name], $options);
    }

    public function mergeOptions(array $options = [])
    {
        $this->setOptions(array_merge_recursive($this->options, $options));
    }

    public function setMappingType($mappingType)
    {
        $this->mappingType = $mappingType;
    }

    public function getMappingType()
    {
        return $this->mappingType;
    }

    /**
     * Camelize a string.
     *
     * NEXT_MAJOR: remove this method.
     *
     * @static
     *
     * @param string $property
     *
     * @return string
     *
     * @deprecated since sonata-project/admin-bundle 3.1. Use \Doctrine\Inflector\Inflector::classify() instead
     */
    public static function camelize($property)
    {
        @trigger_error(sprintf(
            'The %s method is deprecated since 3.1 and will be removed in 4.0. Use %s::classify() instead.',
            __METHOD__,
            Inflector::class
        ), E_USER_DEPRECATED);

        return InflectorFactory::create()->build()->classify($property);
    }

    /**
     * Defines the help message.
     *
     * NEXT_MAJOR: Remove this method.
     *
     * @deprecated since sonata-project/admin-bundle 3.74 and will be removed in version 4.0. Use Symfony Form "help" option instead.
     *
     * @param string $help
     */
    public function setHelp($help)
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[1] ?? null)) {
            @trigger_error(sprintf(
                'The "%s()" method is deprecated since sonata-project/admin-bundle 3.74 and will be removed in version 4.0.'
                .' Use Symfony Form "help" option instead.',
                __METHOD__
            ), E_USER_DEPRECATED);
        }

        $this->help = $help;
    }

    /**
     * NEXT_MAJOR: Remove this method.
     *
     * @deprecated since sonata-project/admin-bundle 3.x and will be removed in version 4.0. Use Symfony Form "help" option instead.
     *
     * @return string
     */
    public function getHelp()
    {
        if ('sonata_deprecation_mute' !== (\func_get_args()[0] ?? null)) {
            @trigger_error(sprintf(
                'The "%s()" method is deprecated since sonata-project/admin-bundle 3.74 and will be removed in version 4.0.'
                .' Use Symfony Form "help" option instead.',
                __METHOD__
            ), E_USER_DEPRECATED);
        }

        return $this->help;
    }

    public function getLabel()
    {
        $label = $this->getOption('label');
        if (null !== $label && false !== $label && !\is_string($label) && 'sonata_deprecation_mute' !== (\func_get_args()[0] ?? null)) {
            @trigger_error(sprintf(
                'Returning other type than string, false or null in method %s() is deprecated since'
                .' sonata-project/admin-bundle 3.65. It will return only those types in version 4.0.',
                __METHOD__
            ), E_USER_DEPRECATED);
        }

        return $label;
    }

    public function isSortable()
    {
        return false !== $this->getOption('sortable', false);
    }

    public function getSortFieldMapping()
    {
        return $this->getOption('sort_field_mapping');
    }

    public function getSortParentAssociationMapping()
    {
        return $this->getOption('sort_parent_association_mappings');
    }

    public function getTranslationDomain()
    {
        return $this->getOption('translation_domain') ?: $this->getAdmin()->getTranslationDomain();
    }

    /**
     * Return true if field is virtual.
     *
     * @return bool
     */
    public function isVirtual()
    {
        return false !== $this->getOption('virtual_field', false);
    }

    private function getFieldGetterKey(object $object, ?string $fieldName): ?string
    {
        if (!\is_string($fieldName)) {
            return null;
        }
        $components = [\get_class($object), $fieldName];
        $code = $this->getOption('code');
        if (\is_string($code) && '' !== $code) {
            $components[] = $code;
        }

        return implode('-', $components);
    }

    private function hasCachedFieldGetter(object $object, string $fieldName): bool
    {
        return isset(
            self::$fieldGetters[$this->getFieldGetterKey($object, $fieldName)]
        );
    }

    private function callCachedGetter(object $object, string $fieldName, array $parameters = [])
    {
        $getterKey = $this->getFieldGetterKey($object, $fieldName);
        if ('getter' === self::$fieldGetters[$getterKey]['method']) {
            return $object->{self::$fieldGetters[$getterKey]['getter']}(...$parameters);
        }

        if ('call' === self::$fieldGetters[$getterKey]['method']) {
            return $object->{$fieldName}(...$parameters);
        }

        return $object->{$fieldName};
    }

    private function cacheFieldGetter(object $object, ?string $fieldName, string $method, ?string $getter = null): void
    {
        $getterKey = $this->getFieldGetterKey($object, $fieldName);
        if (null !== $getterKey) {
            self::$fieldGetters[$getterKey] = [
                'method' => $method,
            ];
            if (null !== $getter) {
                self::$fieldGetters[$getterKey]['getter'] = $getter;
            }
        }
    }
}
