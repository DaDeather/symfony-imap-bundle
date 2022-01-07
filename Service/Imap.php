<?php

namespace DaDaDev\ImapBundle\Service;

use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\Mailbox;

class Imap
{
    protected array $connections;

    /**
     * @var array|Mailbox[]
     */
    protected array $instances = [];

    public function __construct(array $connections)
    {
        $this->connections = $connections;
    }

    /**
     * Get a connection to the specified mailbox.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws InvalidParameterException
     */
    public function get(string $name, bool $newInstance = false): Mailbox
    {
        if ($newInstance === true || !isset($this->instances[$name])) {
            $this->instances[$name] = $this->getMailbox($name);
        }

        return $this->instances[$name];
    }

    /**
     * Test mailbox connection.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws InvalidParameterException
     */
    public function testConnection(string $name, bool $bubbleUpExceptions = false): bool
    {
        try {
            return $this->getMailbox($name)->getImapStream(true) !== null;
        } catch (\Exception $exception) {
            if ($bubbleUpExceptions) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * Get new mailbox instance.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws InvalidParameterException
     */
    protected function getMailbox(string $name): Mailbox
    {
        if (!isset($this->connections[$name])) {
            throw new \InvalidArgumentException(sprintf('Imap connection %s is not configured.', $name));
        }

        $config = $this->connections[$name];
        if (isset($config['attachments_dir'])) {
            $this->checkAttachmentsDir($config['attachments_dir']);
        }

        return new Mailbox(
            $config['mailbox'],
            $config['username'],
            $config['password'],
            $config['attachments_dir'] ?? null,
            $config['server_encoding'] ?? 'UTF-8'
        );
    }

    /**
     * Check attachment's directory.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function checkAttachmentsDir(string $directoryPath, bool $createIfNotExists = true): void
    {
        $directoryPath = trim($directoryPath);
        if ($directoryPath === '') {
            return;
        }

        if (file_exists($directoryPath)) {
            if (!is_dir($directoryPath)) {
                throw new \InvalidArgumentException(sprintf('File "%s" exists but it is not a directory', $directoryPath));
            }

            if (!is_readable($directoryPath) || !is_writable($directoryPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" does not have expected access permissions', $directoryPath));
            }

            return;
        }

        if (
            $createIfNotExists === true
            && !mkdir($directoryPath, 0770, true)
            && !is_dir($directoryPath)
        ) {
            throw new \RuntimeException(sprintf('Cannot create the attachments directory "%s"', $directoryPath));
        }
    }
}
