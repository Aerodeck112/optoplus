<?php

namespace App\Modules\I18n\Mail;

use App\Modules\I18n\Services\HtmlTranslator;
use App\Modules\I18n\Support\Locales;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

/**
 * Un email trimis în altă limbă decât româna (notificarea comenzii setează limba comenzii) se traduce cu același
 * dicționar ca siteul: subiectul, textul HTML și varianta text. Adresele interne primesc prefixul limbii.
 */
class TranslateOutgoingMail
{
    public function __construct(private HtmlTranslator $translator) {}

    public function handle(MessageSending $event): void
    {
        $locale = app()->getLocale();
        if (! Locales::isTranslated($locale) || ! $event->message instanceof Email) {
            return;
        }
        $m = $event->message;
        if ($subject = $m->getSubject()) {
            $m->subject($this->translator->plain($subject, $locale));
        }
        if (is_string($html = $m->getHtmlBody())) {
            $m->html($this->translator->translate($html, $locale));
        }
        if (is_string($text = $m->getTextBody())) {
            $m->text(implode("\n", array_map(fn ($line) => trim($line) === '' ? $line : $this->translator->plain($line, $locale), explode("\n", $text))));
        }
    }
}
