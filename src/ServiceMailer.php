<?php

declare(strict_types=1);

namespace Yii\Extension\Service;

use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Nyholm\Psr7\UploadedFile;
use Yii\Extension\Service\Event\MessageSent;
use Yii\Extension\Service\Event\MessageNotSent;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Mailer\Composer;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;

final class ServiceMailer
{
    private Aliases $aliases;
    private Composer $composer;
    private EventDispatcherInterface $dispatch;
    private LoggerInterface $logger;
    private MailerInterface $mailer;

    public function __construct(
        Aliases $aliases,
        Composer $composer,
        EventDispatcherInterface $dispatch,
        LoggerInterface $logger,
        MailerInterface $mailer
    ) {
        $this->aliases = $aliases;
        $this->composer = $composer;
        $this->dispatch = $dispatch;
        $this->logger = $logger;
        $this->mailer = $mailer;
    }

    public function run(
        string $from,
        string $to,
        string $subject,
        string $viewPath,
        array $layout = [],
        array $params = [],
        iterable $attachFiles = []
    ): bool {
        $this->composer->setViewPath($this->aliases->get($viewPath));

        $message = $this->mailer->compose($layout, ['params' => $params])
            ->setFrom($from)
            ->setSubject($subject)
            ->setTo($to);

        /** @var array $attachFile */
        foreach ($attachFiles as $attachFile) {
            /** @var UploadedFile $file */
            foreach ($attachFile as $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $message->attachContent(
                        (string) $file->getStream(),
                        [
                            'fileName' => $file->getClientFilename(),
                            'contentType' => $file->getClientMediaType(),
                        ]
                    );
                }
            }
        }

        return $this->send($message);
    }

    private function send(MessageInterface $message): bool
    {
        $result = false;

        try {
            $this->mailer->send($message);
            $event = new MessageSent();
            $this->dispatch->dispatch($event);
            $result = true;
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->logger->error($message);
            $event = new MessageNotSent($message);
            $this->dispatch->dispatch($event);
        }

        return $result;
    }
}
