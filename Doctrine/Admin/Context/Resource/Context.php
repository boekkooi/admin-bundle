<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Bundle\AdminBundle\Doctrine\Admin\Context\Resource;

use FSi\Bundle\AdminBundle\Admin\Context\ContextInterface;
use FSi\Bundle\AdminBundle\Admin\Context\Request\HandlerInterface;
use FSi\Bundle\AdminBundle\Doctrine\Admin\ResourceElement;
use FSi\Bundle\AdminBundle\Event\FormEvent;
use FSi\Bundle\AdminBundle\Exception\ContextException;
use FSi\Bundle\ResourceRepositoryBundle\Repository\MapBuilder;
use FSi\Bundle\ResourceRepositoryBundle\Repository\Resource\Type\ResourceInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Norbert Orzechowicz <norbert@fsi.pl>
 */
class Context implements ContextInterface
{
    /**
     * @var HandlerInterface[]
     */
    private $requestHandlers;

    /**
     * @var ResourceElement
     */
    private $element;

    /**
     * @var MapBuilder
     */
    private $mapBuilder;

    /**
     * @var \Symfony\Component\Form\FormFactory
     */
    private $formFactory;

    /**
     * @var \Symfony\Component\Form\Form
     */
    private $form;

    /**
     * @param $requestHandlers
     * @param \Symfony\Component\Form\FormFactoryInterface $formFactory
     * @param \FSi\Bundle\ResourceRepositoryBundle\Repository\MapBuilder $mapBuilder
     */
    function __construct($requestHandlers, FormFactoryInterface $formFactory, MapBuilder $mapBuilder = null)
    {
        $this->requestHandlers = $requestHandlers;
        $this->formFactory = $formFactory;
        $this->mapBuilder = $mapBuilder;
    }

    /**
     * @param ResourceElement $element
     */
    public function setElement(ResourceElement $element)
    {
        $this->element = $element;
        $this->createForm();
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(Request $request)
    {
        $event = new FormEvent($this->element, $request, $this->form);

        foreach ($this->requestHandlers as $handler) {
            $response = $handler->handleRequest($event, $request);
            if (isset($response)) {
                return $response;
            }
        }
    }

    /**
     * @return boolean
     */
    public function hasTemplateName()
    {
        return $this->element->hasOption('template');
    }

    /**
     * @return string
     */
    public function getTemplateName()
    {
        return $this->element->getOption('template');
    }

    /**
     * @return array
     */
    public function getData()
    {
        return array(
            'form' => $this->form->createView(),
            'element' => $this->element,
            'title' => $this->element->getOption('title')
        );
    }

    /**
     * @throws \FSi\Bundle\AdminBundle\Exception\ContextBuilderException
     */
    private function createForm()
    {
        $resources = $this->getResourceGroup($this->element);

        if (!is_array($resources)) {
            throw new ContextException(sprintf('%s its not a resource group key', $this->element->getKey()));
        }

        $builder = $this->formFactory->createBuilder(
            'form',
            $this->createFormData($resources),
            $this->element->getResourceFormOptions()
        );

        $this->buildForm($builder, $resources);
        $this->form = $builder->getForm();
    }

    /**
     * @param ResourceElement $element
     * @return mixed
     */
    private function getResourceGroup(ResourceElement $element)
    {
        $map = $this->mapBuilder->getMap();

        $parts = explode('.', $element->getKey());
        $propertyPath = '';

        foreach ($parts as $part) {
            $propertyPath .= sprintf("[%s]", $part);
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        return $accessor->getValue($map, $propertyPath);
    }

    /**
     * @param array $resources
     * @return array
     */
    private function createFormData(array $resources)
    {
        $data = array();

        foreach ($resources as $resourceKey => $resource) {
            if ($resource instanceof ResourceInterface) {
                $resourceName = $this->buildResourceName($resourceKey);
                $data[$this->normalizeKey($resourceName)]
                    = $this->element->getRepository()->get($resource->getName());
            }
        }

        return $data;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $resources
     */
    private function buildForm(FormBuilderInterface $builder, array $resources)
    {
        foreach ($resources as $resourceKey => $resource) {
            if ($resource instanceof ResourceInterface) {
                $resourceName = $this->buildResourceName($resourceKey);
                $builder->add(
                    $this->normalizeKey($resourceName),
                    'resource',
                    array(
                        'resource_key' => $resourceName,
                    )
                );
            }
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private function normalizeKey($key)
    {
        return str_replace('.', '_', $key);
    }

    /**
     * @param string $resourceKey
     * @return string
     */
    private function buildResourceName($resourceKey)
    {
        return sprintf("%s.%s", $this->element->getKey(), $resourceKey);
    }
}
