<?php

namespace Webmavens\DebugMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;

/**
 * Sent when a rule that triggered a failure alert passes again.
 */
class DebugRuleRecovered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DebugRule $rule) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Debug Rule Recovered: {$this->rule->name}")
            ->line('A debug rule that was failing is passing again.')
            ->line("Rule: {$this->rule->name}")
            ->line("Importance: {$this->rule->importance_level}")
            ->action('View Debug Monitor', route('debug-monitor.rules.show', $this->rule->id));
    }
}
