{{-- Shared MDM document typography. Tables only — DomPDF does not layout flex/grid reliably. --}}
<style>
    @page {
        margin: 72px 54px 80px 54px;
    }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        color: #1c1917;
        font-size: 10.5pt;
        line-height: 1.4;
        margin: 0;
        background: #fff;
    }
    .doc-header {
        width: 100%;
        border-bottom: 2px solid #1e3a5f;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .doc-header td { vertical-align: top; }
    .mark {
        width: 42px;
        height: 42px;
        background: #1e3a5f;
        color: #fff;
        text-align: center;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 0.04em;
        line-height: 42px;
    }
    .brand-name {
        font-size: 9pt;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #57534e;
        padding-left: 12px;
    }
    .agency {
        font-size: 13pt;
        font-weight: 700;
        color: #1e3a5f;
        padding-left: 12px;
        padding-top: 2px;
    }
    .doc-title {
        text-align: right;
        font-size: 14pt;
        font-weight: 700;
        color: #1c1917;
    }
    .doc-sub {
        text-align: right;
        font-size: 10pt;
        color: #44403c;
        padding-top: 2px;
    }
    .doc-ref {
        text-align: right;
        font-size: 8.5pt;
        color: #78716c;
        padding-top: 2px;
    }
    h2.section {
        font-size: 10pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #1e3a5f;
        border-bottom: 1px solid #d6d3d1;
        padding-bottom: 4px;
        margin: 18px 0 8px;
    }
    table.facts {
        width: 100%;
        border-collapse: collapse;
    }
    table.facts td {
        width: 25%;
        padding: 6px 10px 8px 0;
        vertical-align: top;
    }
    .fact-label {
        display: block;
        font-size: 7.5pt;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #78716c;
        padding-bottom: 2px;
    }
    .fact-value { font-size: 10.5pt; }
    table.rows {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }
    table.rows th {
        text-align: left;
        font-size: 7.5pt;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #57534e;
        border-bottom: 1px solid #d6d3d1;
        padding: 5px 6px;
    }
    table.rows td {
        border-bottom: 1px solid #e7e5e4;
        padding: 6px;
        vertical-align: top;
        font-size: 9.5pt;
    }
    .badge {
        display: inline-block;
        border: 1px solid #a8a29e;
        padding: 1px 6px;
        font-size: 8pt;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #44403c;
        white-space: nowrap;
    }
    .note {
        border: 1px solid #e7e5e4;
        padding: 8px 10px;
        min-height: 48px;
        white-space: pre-wrap;
    }
    .blank-notes {
        border: 1px solid #d6d3d1;
        height: 88px;
        margin-top: 4px;
    }
    .muted { color: #78716c; }
    .small { font-size: 8.5pt; }
    .ack {
        margin-top: 18px;
        width: 100%;
        border-collapse: collapse;
    }
    .ack td {
        width: 50%;
        padding: 18px 18px 0 0;
        font-size: 9pt;
        color: #57534e;
    }
    .ack .line {
        border-top: 1px solid #a8a29e;
        margin-top: 28px;
        padding-top: 4px;
    }
    .doc-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -56px;
        height: 48px;
        font-size: 7.5pt;
        color: #57534e;
        border-top: 1px solid #d6d3d1;
        padding-top: 6px;
    }
    .doc-footer table { width: 100%; }
    .preview-toolbar {
        background: #1e3a5f;
        color: #fff;
        padding: 10px 16px;
        margin: -8px -8px 24px;
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 13px;
    }
    .preview-toolbar a, .preview-toolbar button {
        color: #fff;
        margin-right: 14px;
        background: transparent;
        border: 1px solid rgba(255,255,255,0.4);
        padding: 6px 10px;
        text-decoration: none;
        cursor: pointer;
        font-size: 13px;
    }
    .preview-shell { max-width: 820px; margin: 0 auto; padding: 24px 28px 48px; }
    @media print {
        .preview-toolbar { display: none; }
        .preview-shell { max-width: none; padding: 0; }
    }
</style>
