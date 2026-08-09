<?php

namespace App\Command;

use App\Entity\Product;
use App\Service\ReportService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:send-daily-summary', description: 'Email the daily sales summary to configured recipients')]
class SendDailySummaryCommand extends Command
{
    public function __construct(
        private ReportService $reports,
        private SettingsService $settings,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Summary date (Y-m-d)', date('Y-m-d'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $date = new \DateTimeImmutable((string) $input->getOption('date') . ' 00:00:00');
        $recipients = array_filter(array_map('trim', explode(',', (string) $this->settings->get('daily_summary_email', ''))));

        if ([] === $recipients) {
            $io->warning('No recipients configured. Set the "daily_summary_email" setting in Admin → Settings.');

            return Command::SUCCESS;
        }

        $summary = $this->reports->dashboard($date);
        $lowStock = $this->em->getRepository(Product::class)->findLowStock();

        $html = $this->renderSummary($date, $summary, $lowStock);

        $email = (new Email())
            ->from(new Address('no-reply@mis-system.local', (string) $this->settings->get('business_name', 'MIS System')))
            ->subject(sprintf('[%s] Daily summary — %s', $this->settings->get('business_name', 'MIS System'), $date->format('Y-m-d')))
            ->html($html);

        foreach ($recipients as $recipient) {
            $email->addTo($recipient);
        }

        $this->mailer->send($email);

        $io->success(sprintf('Daily summary for %s sent to %s.', $date->format('Y-m-d'), implode(', ', $recipients)));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, string|int> $summary
     * @param array<int, Product>       $lowStock
     */
    private function renderSummary(\DateTimeImmutable $date, array $summary, array $lowStock): string
    {
        $currency = $this->settings->get('currency', 'USD') . ' ';
        $fmt = static fn (string $value): string => $currency . number_format((float) $value, 2);
        $row = static fn (string $label, string $value): string => sprintf(
            '<tr><td style="padding:10px 14px;border-bottom:1px solid #eef0f6;color:#475569;">%s</td><td style="padding:10px 14px;border-bottom:1px solid #eef0f6;text-align:right;font-weight:700;color:#0f172a;">%s</td></tr>',
            $label,
            $value,
        );

        $lowStockRows = '';
        foreach ($lowStock as $product) {
            $lowStockRows .= sprintf(
                '<tr><td style="padding:8px 14px;border-bottom:1px solid #eef0f6;color:#475569;">%s</td><td style="padding:8px 14px;border-bottom:1px solid #eef0f6;text-align:right;font-weight:600;color:#e11d48;">%s left</td></tr>',
                $product->getName(),
                $product->getCurrentStock(),
            );
        }
        if ('' === $lowStockRows) {
            $lowStockRows = '<tr><td colspan="2" style="padding:10px 14px;color:#94a3b8;">All products are well stocked.</td></tr>';
        }

        return <<<HTML
        <div style="background:#f4f6fb;padding:32px;font-family:Inter,Arial,sans-serif;">
            <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e8ecf3;border-radius:16px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#17143a,#4c1d95);padding:24px 28px;color:#fff;">
                    <div style="font-size:20px;font-weight:700;">{$this->settings->get('business_name', 'MIS System')}</div>
                    <div style="font-size:13px;opacity:.75;">Daily summary — {$date->format('l, j F Y')}</div>
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    {$row('Today\'s sales', $fmt((string) $summary['today_sales']))}
                    {$row('Today\'s orders', (string) $summary['today_orders'])}
                    {$row('Today\'s expenses', $fmt((string) $summary['today_expenses']))}
                    {$row('Today\'s profit', $fmt((string) $summary['today_profit']))}
                    {$row('Month sales', $fmt((string) $summary['month_sales']))}
                    {$row('Month expenses', $fmt((string) $summary['month_expenses']))}
                    {$row('Month profit', $fmt((string) $summary['month_profit']))}
                </table>
                <div style="padding:20px 28px 6px;font-size:14px;font-weight:700;color:#0f172a;">Low stock alerts</div>
                <table style="width:100%;border-collapse:collapse;">{$lowStockRows}</table>
                <div style="padding:20px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #eef0f6;margin-top:8px;">
                    Generated by the {$this->settings->get('business_name', 'MIS System')} management system.
                </div>
            </div>
        </div>
        HTML;
    }
}
