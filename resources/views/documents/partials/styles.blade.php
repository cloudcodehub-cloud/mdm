{{-- Shared MDM document typography. Tables only — DomPDF does not layout flex/grid reliably. --}}
<style>
    @page {
        margin: 56px 48px 64px 48px;
    }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        color: #1c2428;
        font-size: 10pt;
        line-height: 1.38;
        margin: 0;
        background: #fff;
    }
    .doc-header {
        width: 100%;
        border-bottom: 2.5px solid #1f6f7a;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .doc-header td { vertical-align: middle; }
    .mark {
        width: 40px;
        height: 40px;
        background: #1f6f7a;
        color: #fff;
        text-align: center;
        font-weight: 700;
        font-size: 10px;
        letter-spacing: 0.04em;
        line-height: 40px;
    }
    .logo {
        max-height: 42px;
        max-width: 120px;
    }
    .brand-name {
        font-size: 8.5pt;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #5b666c;
        padding-left: 10px;
    }
    .agency {
        font-size: 13pt;
        font-weight: 700;
        color: #16343a;
        padding-left: 10px;
        padding-top: 1px;
    }
    .doc-title {
        text-align: right;
        font-size: 13.5pt;
        font-weight: 700;
        color: #16343a;
        line-height: 1.2;
    }
    .doc-sub {
        text-align: right;
        font-size: 9.5pt;
        color: #3d4a50;
        padding-top: 2px;
    }
    .doc-ref {
        text-align: right;
        font-size: 8pt;
        color: #6b757a;
        padding-top: 2px;
    }
    h2.section {
        font-size: 9pt;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #1f6f7a;
        border-bottom: 1px solid #d5dde0;
        padding-bottom: 3px;
        margin: 14px 0 6px;
        page-break-after: avoid;
    }
    table.facts {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    table.facts td {
        width: 25%;
        padding: 4px 12px 6px 0;
        vertical-align: top;
    }
    .fact-label {
        display: block;
        font-size: 7pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b757a;
        padding-bottom: 1px;
    }
    .fact-value { font-size: 10pt; font-weight: 500; }
    table.rows {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2px;
    }
    table.rows thead { display: table-header-group; }
    table.rows tr { page-break-inside: avoid; }
    table.rows th {
        text-align: left;
        font-size: 7pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #3d4a50;
        border-bottom: 1.5px solid #1f6f7a;
        padding: 4px 6px;
        background: #f4f8f8;
    }
    table.rows td {
        border-bottom: 1px solid #e4eaec;
        padding: 5px 6px;
        vertical-align: top;
        font-size: 9pt;
    }
    .badge {
        display: inline-block;
        border: 1px solid #9aa7ac;
        padding: 0 5px;
        font-size: 7.5pt;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #3d4a50;
        white-space: nowrap;
        line-height: 1.6;
    }
    .note {
        border: 1px solid #d5dde0;
        background: #fafcfc;
        padding: 7px 9px;
        min-height: 36px;
        white-space: pre-wrap;
        page-break-inside: avoid;
    }
    .blank-notes {
        border: 1px solid #c5d0d4;
        height: 72px;
        margin-top: 3px;
        background:
            repeating-linear-gradient(
                to bottom,
                transparent,
                transparent 17px,
                #e7eef0 18px
            );
        page-break-inside: avoid;
    }
    .muted { color: #6b757a; }
    .small { font-size: 8pt; }
    .ack {
        margin-top: 14px;
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    .ack td {
        width: 50%;
        padding: 14px 16px 0 0;
        font-size: 8.5pt;
        color: #3d4a50;
    }
    .ack .line {
        border-top: 1px solid #9aa7ac;
        margin-top: 22px;
        padding-top: 3px;
    }
    .doc-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -44px;
        height: 40px;
        font-size: 7pt;
        color: #3d4a50;
        border-top: 1px solid #d5dde0;
        padding-top: 5px;
    }
    .doc-footer table { width: 100%; }
</style>
