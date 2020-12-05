<?php

declare(strict_types=1);

namespace Yii\Extension\Service\Tests;

use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swift_Plugins_LoggerPlugin;
use Swift_SmtpTransport;
use Swift_Transport;
use Yii\Extension\Service\MailerService;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Di\Container;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Factory\Definitions\Reference;
use Yiisoft\Files\FileHelper;
use Yiisoft\Log\Logger as YiiLogger;
use Yiisoft\Mailer\Composer;
use Yiisoft\Mailer\FileMailer;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageFactory;
use Yiisoft\Mailer\MessageFactoryInterface;
use Yiisoft\Mailer\SwiftMailer\Logger;
use Yiisoft\Mailer\SwiftMailer\Mailer;
use Yiisoft\Mailer\SwiftMailer\Message;
use Yiisoft\View\WebView;

final class MailerServiceTest extends TestCase
{
    private Aliases $aliases;
    private ContainerInterface $container;
    private MailerService $mailer;
    private bool $writeToFiles = true;

    public function testMailer(): void
    {
        $this->container = new Container($this->config());
        $this->aliases = $this->container->get(Aliases::class);
        $this->mailer = $this->container->get(MailerService::class);

        $this->assertTrue(
            $this->mailer->run(
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

        unset($this->aliases, $this->container, $this->mailer);
    }

    public function testMailerException(): void
    {
        $this->writeToFiles = false;
        $this->container = new Container($this->config());
        $this->aliases = $this->container->get(Aliases::class);
        $this->mailer = $this->container->get(MailerService::class);

        $this->assertFalse(
            $this->mailer->run(
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

        unset($this->aliases, $this->container, $this->mailer);
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

            LoggerInterface::class => YiiLogger::class,

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

            MailerService::class => MailerService::class,
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
