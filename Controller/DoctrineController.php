<?php

namespace Liip\VieBundle\Controller;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response,
    Symfony\Component\Routing\Exception\ResourceNotFoundException;

use FOS\RestBundle\View\ViewHandlerInterface,
    FOS\RestBundle\View\View,
    FOS\RestBundle\Response\Codes;

use Doctrine\Common\Persistence\ObjectManager;

use Liip\VieBundle\FromJsonLdInterface,
    Liip\VieBundle\ToJsonLdInterface;

abstract class DoctrineController
{
    /**
     * @var FOS\RestBundle\View\ViewHandlerInterface
     */
    protected $viewHandler;

    /**
     * @var Doctrine\Common\Persistence\ObjectManager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $map;

    public function __construct(ViewHandlerInterface $viewHandler, ObjectManager $manager, array $map)
    {
        $this->viewHandle = $viewHandler;
        $this->manager = $manager;
        $this->map = $map;
    }

    /**
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @param array $data
     * @return \Doctrine\ODM\PHPCR\DocumentRepository
     */
    protected function getRepository($data)
    {
        if (empty($this->map[$data['a']])) {
            throw new ResourceNotFoundException($data['a'].' is not mapped to a class');
        }

        return $this->manager->getRepository($this->map[$data['a']]);
    }

    /**
     * Handle article PUT
     */
    public function putDocumentAction(Request $request, $id)
    {
        $data = $request->request->all();

        $repository = $this->getRepository($data);
        $model = $repository->find($id);
        if (empty($model)) {
            throw new ResourceNotFoundException($id.' not found');
        }

        if (!($model instanceof FromJsonLdInterface)) {
            throw new \LogicException('Document does not support FromJsonLdInterface.');
        }

        $model->fromJsonLD($data);
        $this->manager->flush();

        if (!($model instanceof ToJsonLdInterface)) {
            return new Response('', Codes::HTTP_NO_CONTENT);
        }

        // return the updated version
        $view = View::create($model->toJsonLd())->setFormat('json');
        return $this->viewHandler->handle($view, $request);
    }
}
