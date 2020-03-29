<?php

declare(strict_types=1);

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - https://ziku.la/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\MailerModule\Helper;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Zikula\Bundle\CoreBundle\Helper\LocalDotEnvHelper;

class MailTransportHelper
{
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function handleFormData($formData): bool
    {
        $transportStrings = [
            'smtp' => 'smtp://$MAILER_ID:$MAILER_KEY@example.com',
            'sendmail' => 'sendmail+smtp://default',
            'amazon' => 'ses://$MAILER_ID:$MAILER_KEY@default',
            'gmail' => 'gmail://$MAILER_ID:$MAILER_KEY@default',
            'mailchimp' => 'mandrill://$MAILER_ID:$MAILER_KEY@default',
            'mailgun' => 'mailgun://$MAILER_ID:$MAILER_KEY@default',
            'postmark' => 'postmark://$MAILER_ID:$MAILER_KEY@default',
            'sendgrid' => 'sendgrid://apikey:$MAILER_KEY@default', // unclear if 'apikey' is supposed to be literal, or replaced
            'test' => 'null://null',
        ];
        try {
            $vars = [
                'MAILER_ID' => $formData['mailer_id'],
                'MAILER_KEY' => $formData['mailer_key'],
                'MAILER_DSN' => '!' . $transportStrings[$formData['transport']]
            ];
            $helper = new LocalDotEnvHelper($this->projectDir);
            $helper->writeLocalEnvVars($vars);

            return true;
        } catch (IOExceptionInterface $exception) {
            return false;
        }
    }
}
