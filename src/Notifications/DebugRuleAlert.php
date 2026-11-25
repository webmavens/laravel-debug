<?php

namespace Webmavens\DebugMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Webmavens\DebugMonitor\Models\DebugRule;

class DebugRuleAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DebugRule $rule, public array $result) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // A query error (e.g. a renamed table) is called out separately from
        // data that doesn't match the rule's expectations.
        $isError = isset($this->result['exception']);
        $isUrgent = $this->rule->notification_level === 'urgent';

        $mail = (new MailMessage)
            ->subject(($isUrgent ? '[Urgent] ' : '').($isError ? 'Debug Rule Error: ' : 'Debug Rule Failed: ').$this->rule->name)
            ->line($isError ? "A debug rule's query could not run." : 'A debug rule has failed.')
            ->line("Rule: {$this->rule->name}")
            ->line("Importance: {$this->rule->importance_level}")
            ->line("Result: " . json_encode($this->result))
            ->action('View Debug Monitor', route('debug-monitor.rules.show', $this->rule->id));

        // X-Priority 1 (highest). The subject prefix is what most mail apps show,
        // since many (e.g. Gmail) ignore the priority header.
        return $isUrgent ? $mail->priority(1) : $mail;
    }
}
