<?php

declare(strict_types=1);

namespace Yii\Extension\Service\Tests;

use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\UploadedFile;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Swift_Plugins_LoggerPlugin;
use Swift_SmtpTransport;
use Swift_Transport;
use Yii\Extension\Service\ServiceMailer;
use Yii\Extension\Service\Event\MessageNotSent;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Di\Container;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Factory\Definitions\Reference;
use Yiisoft\Files\FileHelper;
use Yiisoft\Mailer\Composer;
use Yiisoft\Mailer\FileMailer;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageFactory;
use Yiisoft\Mailer\MessageFactoryInterface;
use Yiisoft\Mailer\SwiftMailer\Logger;
use Yiisoft\Mailer\SwiftMailer\Mailer;
use Yiisoft\Mailer\SwiftMailer\Message;
use Yiisoft\View\WebView;

final class ServiceMailerTest extends TestCase
{
    private Aliases $aliases;
    private ContainerInterface $container;
    private ServiceMailer $serviceMailer;
    private bool $writeToFiles = true;

    public function testServiceMailer(): void
    {
        $this->container = new Container($this->config());
        $this->aliases = $this->container->get(Aliases::class);
        $this->serviceMailer = $this->container->get(ServiceMailer::class);

        $this->assertTrue(
            $this->serviceMailer->run(
                'test@example.com',
                'admin1@example.com',
                'TestMe',
                '@mail',
                [ 'html' => 'contact'],
                [
                    'username' => 'User',
                    'body' => 'TestMe',
                ],
                [
                    [
                        new UploadedFile(__DIR__ . '/resources/data/foo.txt', 0, UPLOAD_ERR_OK),
                    ],
                ],
            )
        );

        $this->removeDirectory('@runtime');

        unset($this->aliases, $this->container, $this->serviceMailer);
    }

    public function testServiceMailerException(): void
    {
        $this->writeToFiles = false;
        $this->container = new Container($this->config());
        $this->aliases = $this->container->get(Aliases::class);
        $this->serviceMailer = $this->container->get(ServiceMailer::class);

        $this->assertFalse(
            $this->serviceMailer->run(
                'test@example.com',
                'admin1@example.com',
                'TestMe',
                '@mail',
                [ 'html' => 'contact'],
                [
                    'username' => 'User',
                    'body' => 'TestMe',
                ],
                [
                    [
                        new UploadedFile(__DIR__ . '/resources/data/foo.txt', 0, UPLOAD_ERR_OK),
                    ],
                ],
            )
        );

        $this->removeDirectory('@runtime');

        unset($this->aliases, $this->container, $this->serviceMailer);
    }

    public function testEventMessageNotSend(): void
    {
        $event = new MessageNotSent('testMe');

        $this->assertEquals('testMe', $event->getErrorMessage());
    }

    private function config(): array
    {
        $params = $this->params();

        return [
            Aliases::class => [
                '__class' => Aliases::class,
                '__construct()' => [
                    [
                        '@mail' => __DIR__ . '/resources/mail',
                        '@resources' => __DIR__ . '/resources',
                        '@runtime' => __DIR__ . '/runtime',
                    ],
                ],
            ],

            EventDispatcherInterface::class => Dispatcher::class,

            ListenerProviderInterface::class => Provider::class,

            LoggerInterface::class => NullLogger::class,

            WebView::class => [
                '__class' => WebView::class,
                '__construct()' => [
                    'basePath' => __DIR__ . '/resources/mail',
                ],
            ],

            Composer::class => [
                '__class' => Composer::class,
                '__construct()' => [
                    Reference::to(WebView::class),
                    fn (Aliases $aliases) => $aliases->get($params['yiisoft/mailer']['composer']['composerView']),
                ],
            ],

            MessageFactory::class => [
                '__class' => MessageFactory::class,
                '__construct()' => [
                    Message::class,
                ],
            ],

            MessageFactoryInterface::class => MessageFactory::class,

            Logger::class => [
                '__class' => Logger::class,
                '__construct()' => [Reference::to(LoggerInterface::class)],
            ],

            Swift_Transport::class => [
                '__class' => Swift_SmtpTransport::class,
                '__construct()' => [
                    $params['swiftmailer/swiftmailer']['SwiftSmtpTransport']['host'],
                    $params['swiftmailer/swiftmailer']['SwiftSmtpTransport']['port'],
                    $params['swiftmailer/swiftmailer']['SwiftSmtpTransport']['encryption'],
                ],
                'setUsername()' => [$params['swiftmailer/swiftmailer']['SwiftSmtpTransport']['username']],
                'setPassword()' => [$params['swiftmailer/swiftmailer']['SwiftSmtpTransport']['password']],
            ],

            Swift_Plugins_LoggerPlugin::class => [
                '__class' => Swift_Plugins_LoggerPlugin::class,
                '__construct()' => [Reference::to(Logger::class)],
            ],

            Mailer::class => [
                '__class' => Mailer::class,
                'registerPlugin()' => [Reference::to(Swift_Plugins_LoggerPlugin::class)],
            ],

            FileMailer::class => [
                '__class' => FileMailer::class,
                '__construct()' => [
                    'path' => fn (Aliases $aliases) => $aliases->get(
                        $params['yiisoft/mailer']['fileMailer']['fileMailerStorage']
                    ),
                ],
            ],

            MailerInterface::class => $this->writeToFiles ? FileMailer::class : Mailer::class,
        ];
    }

    private function params(): array
    {
        return [
            'yiisoft/mailer' => [
                'composer' => [
                    'composerView' => '@resources/mail',
                ],
                'fileMailer' => [
                    'fileMailerStorage' => '@runtime/mail',
                ],
            ],
            'swiftmailer/swiftmailer' => [
                'SwiftSmtpTransport' => [
                    'host' => 'smtp.example.com',
                    'port' => 25,
                    'encryption' => null,
                    'username' => 'admin@example.com',
                    'password' => '',
                ],
            ],
        ];
    }

    protected function removeDirectory(string $basePath): void
    {
        $handle = opendir($dir = $this->aliases->get($basePath));

        if ($handle === false) {
            throw new RuntimeException("Unable to open directory: $dir");
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.gitignore') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                FileHelper::removeDirectory($path);
            } else {
                FileHelper::unlink($path);
            }
        }

        closedir($handle);
    }
}
