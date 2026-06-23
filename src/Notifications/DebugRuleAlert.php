<?php

namespace Webmavens\DebugMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Webmavens\DebugMonitor\Models\DebugRule;

class DebugRuleAlert extends Notification
{
    use Queueable;

    public function __construct(public DebugRule $rule, public array $result) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Debug Rule Failed: {$this->rule->name}")
            ->line("A debug rule has failed.")
            ->line("Rule: {$this->rule->name}")
            ->line("Importance: {$this->rule->importance_level}")
            ->line("Result: " . json_encode($this->result))
            ->action('View Debug Monitor', route('debug-monitor.rules.show', $this->rule->id));
    }
}
