<?php

namespace App\Services\Documents;

use App\Models\GeneratedDocument;
use Dompdf\Dompdf;
use Dompdf\Options;

class MarketplaceDocumentRenderer
{
    public function merge(string $html, array $payload): string
    {
        $htmlFields = ['payment_schedule', 'product_attributes', 'product_security_state', 'checkpoint_summary'];

        foreach ($payload as $key => $value) {
            $rendered = in_array($key, $htmlFields, true)
                ? (string) $value
                : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = str_replace('{{'.$key.'}}', $rendered, $html);
        }

        return $html;
    }

    public function pdf(GeneratedDocument $document): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($document->rendered_html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
