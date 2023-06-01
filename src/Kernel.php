<?php

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends \Symfony\Component\HttpKernel\Kernel
{
    use MicroKernelTrait;

    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Playbloom\Satisfy\PlaybloomSatisfyBundle(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        try {
            $loader->load(__DIR__ . '/../config/config_' . $this->environment . '.yml');

            return;
        } catch (FileLocatorFileNotFoundException $e) {
        }

        $loader->load(__DIR__ . '/../config/config.yml');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $controllerBase = 'Playbloom\Satisfy\Controller\\';
        $routes->add('/', 'kernel:indexAction', 'index');

        // 1.增加下载接口
        $routes->add('/download/{path}', 'kernel:downloadAction', 'download')
            ->setMethods(['GET', 'POST'])
            ->setRequirement('path', '.*');
        // security
        $routes->add('/login', 'Playbloom\Satisfy\Controller\SecurityController::loginAction', 'login');
        // repository
        $controller = $controllerBase . 'RepositoryController';
        $routes->add('/admin', $controller . '::indexAction', 'repository')->setMethods(['GET']);
        $routes->add('/admin/', $controller . '::indexAction', 'repository_')->setMethods(['GET']);
        $routes->add('/admin/new', $controller . '::newAction', 'repository_new')->setMethods(['GET', 'POST']);
        $routes->add('/admin/upload', $controller . '::uploadAction', 'repository_upload')->setMethods(['GET', 'POST']);
        $routes
            ->add('/admin/edit/{repository}', $controller . '::editAction', 'repository_edit')
            ->setMethods(['GET', 'POST'])
            ->setRequirement('repository', '[a-zA-Z0-9_-]+');
        $routes
            ->add('/admin/delete/{repository}', $controller . '::deleteAction', 'repository_delete')
            ->setMethods(['GET', 'DELETE'])
            ->setRequirement('repository', '[a-zA-Z0-9_-]+');
        $routes
            ->add('/admin/configuration', $controllerBase . 'ConfigurationController::indexAction', 'configuration')
            ->setMethods(['GET', 'POST']);
        // satis interaction
        $routes
            ->add('/admin/satis/build', $controllerBase . 'SatisController::buildAction', 'satis_build')
            ->setMethods(['GET']);
        $routes
            ->add('/admin/satis/buildRun', $controllerBase . 'SatisController::buildRunAction', 'satis_build_run')
            ->setMethods(['GET']);
        // webhooks
        $routes
            ->add('/webhook/bitbucket', $controllerBase . 'WebhookController::bitbucketAction', 'webhook_bitbucket')
            ->setMethods(['GET', 'POST']);
        $routes
            ->add('/webhook/github', $controllerBase . 'WebhookController::githubAction', 'webhook_github')
            ->setMethods(['GET', 'POST']);
        $routes
            ->add('/webhook/gitea', $controllerBase . 'WebhookController::giteaAction', 'webhook_gitea')
            ->setMethods(['GET', 'POST']);
        $routes
            ->add('/webhook/gitlab', $controllerBase . 'WebhookController::gitlabAction', 'webhook_gitlab')
            ->setMethods(['GET', 'POST']);
        $routes
            ->add('/webhook/devops', $controllerBase . 'WebhookController::devopsAction', 'webhook_devops')
            ->setMethods(['GET', 'POST']);
    }

    // optional, to use the standard Symfony cache directory
    public function getCacheDir()
    {
        return __DIR__ . '/../var/cache/' . $this->getEnvironment();
    }

    // optional, to use the standard Symfony logs directory
    public function getLogDir()
    {
        return __DIR__ . '/../var/log';
    }

    /**
     * @return Response
     */
    public function indexAction()
    {
        $indexFile = __DIR__ . '/../public/index.html';
        if (!file_exists($indexFile)) {
            return $this->getContainer()->get('templating')->renderResponse('unavailable.html.twig');
        }

        return new Response(file_get_contents($indexFile));
    }

    /**
     * @return Response
     */
    public function downloadAction(Request $request)
    {
        $path = $request->attributes->get('path');
        // 加入权限校验
        $response = new Response();
        $user = $request->server->get('PHP_AUTH_USER');
        $password = $request->server->get('PHP_AUTH_PW');
        $users = $this->container->getParameter('dist.users');

        if (!isset($users[$user]) || $password !== $users[$user]['password']) {
            $response->setStatusCode(401, '未授权');
            $response->headers->set('WWW-Authenticate', 'Basic realm="satis"');
            return $response;
        }
        $response->headers->add([
            'Content-Disposition' => "attachment; filename=" . basename($path),
            'Content-Type' => 'application/octet-stream',
            'X-Accel-Redirect' => '/dist2/' . $path,
        ]);
        return $response;
    }

}
