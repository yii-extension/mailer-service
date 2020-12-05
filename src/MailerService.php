<?php

declare(strict_types=1);

namespace Yii\Extension\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Mailer\Composer;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\MessageInterface;

final class MailerService
{
    private Aliases $aliases;
    private Composer $composer;
    private LoggerInterface $logger;
    private MailerInterface $mailer;

    public function __construct(
        Aliases $aliases,
        Composer $composer,
        LoggerInterface $logger,
        MailerInterface $mailer
    ) {
        $this->aliases = $aliases;
        $this->composer = $composer;
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

        foreach ($attachFiles as $attachFile) {
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
            $result = true;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $result;
    }
}
