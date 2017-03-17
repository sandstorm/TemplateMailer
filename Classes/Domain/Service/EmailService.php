<?php
namespace Sandstorm\TemplateMailer\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\FluidAdaptor\View\StandaloneView;
use Neos\SwiftMailer\Message;
use Pelago\Emogrifier;
use Sandstorm\TemplateMailer\Exception;

/**
 * @Flow\Scope("singleton")
 */
class EmailService
{
    /**
     * @Flow\Inject
     * @var \Neos\Flow\Log\SystemLoggerInterface
     */
    protected $systemLogger;
    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="senderAddresses")
     */
    protected $senderAddresses;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="templatePackages")
     */
    protected $templatePackages;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="defaultTemplateVariables")
     */
    protected $defaultTemplateVariables;

    /**
     * @param string $templateName name of the email template to use @see renderEmailBody()
     * @param string $subject subject of the email
     * @param array $recipient recipient of the email in the format array('<emailAddress>')
     * @param array $variables variables that will be available in the email template. in the format array('<key>' => '<value>', ....)
     * @param string|array $sender Either an array with the format ['address@example.com' => 'Sender Name'], or a string which identifies a configured sender address
     * @return boolean TRUE on success, otherwise FALSE
     */
    public function sendTemplateEmail(string $templateName, string $subject, array $recipient, array $variables = [], $sender = 'default'): bool
    {
        $targetPackage = $this->findFirstPackageContainingEmailTemplate($templateName);
        $variables = $this->addDefaultTemplateVariables($variables);
        $plaintextBody = $this->renderEmailBody($templateName, $targetPackage, 'txt', $variables);
        $htmlBody = $this->renderEmailBody($templateName, $targetPackage, 'html', $variables);

        $senderAddress = $this->resolveSenderAddress($sender);

        $mail = new Message();
        $mail->setFrom($senderAddress)
            ->setTo($recipient)
            ->setSubject($subject)
            ->setBody($plaintextBody)
            ->addPart($htmlBody, 'text/html');

        return $this->sendMail($mail);
    }

    /**
     * Renders the email body of a template. Can be used directly to only render the email html body.
     * if the given format is htm or html, will automatically emogrify the email body.
     * This can be skipped by setting the last parameter to false.
     *
     * @param string $templateName
     * @param string $templateName
     * @param string $format
     * @param array $variables
     * @param bool $emogrify
     * @return string
     */
    public function renderEmailBody(string $templateName, string $templatePackage, string $format, array $variables, bool $emogrify = true): string
    {
        $standaloneView = new StandaloneView();
        $request = $standaloneView->getRequest();
        //        $request->setControllerPackageKey('Sandstorm.Maklerapp'); TODO needed?
        $request->setFormat($format);

        $templatePath = sprintf('resource://%s/Private/EmailTemplates/', $templatePackage);
        $templatePathAndFilename = sprintf('%s%s.%s', $templatePath, $templateName, $format);
        $standaloneView->setTemplatePathAndFilename($templatePathAndFilename);
        $standaloneView->setLayoutRootPath($templatePath . 'Layouts');
        $standaloneView->setPartialRootPath($templatePath . 'Partials');
        $standaloneView->assignMultiple($variables);
        $emailBody = $standaloneView->render();

        // Emogrify - this will inline any styles in the HTML for proper display in Gmail.
        $emogrifiedFormats = ['htm', 'html'];
        if ($emogrify && in_array($format, $emogrifiedFormats)) {
            $emogrifier = new Emogrifier($emailBody);
            $emailBody = $emogrifier->emogrify();
        }

        return $emailBody;
    }

    /**
     * @param array $passedVariables the template variables passed by the call to sendTemplateEmail().
     * @return array The passed array, merged with the default variables.
     */
    protected function addDefaultTemplateVariables(array $passedVariables): array
    {
        $defaultVariables = [];
        foreach ($this->defaultTemplateVariables as $variableName => $configurationPath) {
            $defaultVariables[$variableName] = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_PROCESSING_TYPE_SETTINGS, $configurationPath);
        }
        return $passedVariables + $defaultVariables;
    }

    /**
     * @param array|string $sender if it's an array, returns it directly. If it's a string, tries to find it in the config and resolve it.
     * @return array
     * @throws Exception
     */
    protected function resolveSenderAddress($sender): array
    {
        if (is_array($sender)) {
            return $sender;
        }
        if (!isset($this->senderAddresses[$sender])) {
            throw new Exception(sprintf('The given sender string was not found in config. Please check config path "Sandstorm.TemplateMailer.senderAddresses.%s".', $sender),
                1489787274);
        }
        if (!isset($this->senderAddresses[$sender]['name']) || !isset($this->senderAddresses[$sender]['address'])) {
            throw new Exception(sprintf('The given sender is not correctly configured - "name" or "address" are missing. Please check config path "Sandstorm.TemplateMailer.senderAddresses.%s".',
                $sender), 1489787274);
        }
        return [$this->senderAddresses[$sender]['address'] => $this->senderAddresses[$sender]['name']];
    }

    /**
     * @param string $templateName
     * @return string
     * @throws Exception
     */
    protected function findFirstPackageContainingEmailTemplate(string $templateName): string
    {
        ksort($this->templatePackages);
        foreach ($this->templatePackages as $package) {
            $templatePath = sprintf('resource://%s/Private/EmailTemplates/%s.html', $package, $templateName);
            if (file_exists($templatePath)) {
                return $package;
            }
        }
        throw new Exception(sprintf('No package containing an email template named "%s" was found. Checked packages: %s', $templateName, implode(', ', $this->templatePackages),
            1489787275));
    }

    /**
     * Sends a mail and creates a system logger entry if sending failed
     *
     * @param Message $mail
     * @return boolean TRUE on success, otherwise FALSE
     */
    protected function sendMail(Message $mail): bool
    {
        $numberOfRecipients = 0;
        // ignore exceptions but log them
        $exceptionMessage = '';
        try {
            $numberOfRecipients = $mail->send();
        } catch (\Exception $e) {
            $this->systemLogger->logException($e);
            $exceptionMessage = $e->getMessage();
        }
        if ($numberOfRecipients < 1) {
            $this->systemLogger->log('Could not send email "' . $mail->getSubject() . '"', LOG_ERR, [
                'exception' => $exceptionMessage,
                'message' => $mail->getSubject(),
                'id' => (string)$mail->getHeaders()->get('Message-ID')
            ]);

            return false;
        }

        return true;
    }
}
