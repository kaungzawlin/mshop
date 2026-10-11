"""Generate a minimal Word .docx API guide without external deps."""

from __future__ import annotations

import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "shop-client" / "public" / "docs" / "mshop-api-guide.docx"
OUT_SERVER = ROOT / "docs" / "mshop-api-guide.docx"

PARAS = [
    ("Title", "mshop API Documentation Guide"),
    ("Heading1", "1. Overview"),
    ("Normal", "mshop is a Myanmar e-commerce platform (Laravel API + Nuxt BFF). Authenticate with Laravel Sanctum bearer tokens. Interactive Swagger UI is available only to system admins at /admin/docs (Nuxt) and GET /api/admin/docs (API)."),
    ("Heading1", "2. Roles"),
    ("Normal", "customer, admin, reseller, rider, ops, finance, responsible, system."),
    ("Normal", "admin: full platform control, settings, banners, messaging, Swagger."),
    ("Normal", "responsible: accountable operations landing and /api/responsible/ping."),
    ("Normal", "system: automation / system-run account and /api/system/ping."),
    ("Heading1", "3. Auth"),
    ("Normal", "POST /api/auth/register — create customer."),
    ("Normal", "POST /api/auth/login — returns token + user."),
    ("Normal", "GET /api/auth/me — current user (Bearer)."),
    ("Normal", "POST /api/auth/logout, /api/auth/logout-all."),
    ("Heading1", "4. Catalog & banners"),
    ("Normal", "GET /api/catalog/categories, /api/catalog/products, /api/catalog/products/{slug}."),
    ("Normal", "GET /api/catalog/banners — currently scheduled active banners."),
    ("Normal", "Admin banners CRUD: /api/admin/banners with starts_at / ends_at schedule."),
    ("Heading1", "5. Cart & checkout"),
    ("Normal", "GET/PATCH /api/cart, POST /api/checkout/quote, POST /api/orders."),
    ("Normal", "Payments: COD, manual_transfer, wallet (controlled by Admin Settings)."),
    ("Heading1", "6. Messaging"),
    ("Normal", "Admin message templates support {{customer_name}} variables, sample send, Excel/CSV campaigns, and message logs."),
    ("Heading1", "7. Admin settings"),
    ("Normal", "Queue, storage, realtime, mail, SMS, payments, deposit, points, delivery, checkout, auth — all ON/OFF via Admin Settings UI (not .env)."),
    ("Heading1", "8. Swagger access"),
    ("Normal", "Only role=admin may call GET /api/admin/openapi.json and GET /api/admin/docs. Non-admins receive 403."),
]


def paragraph(style: str, text: str) -> str:
    return (
        f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr>'
        f"<w:r><w:t xml:space=\"preserve\">{escape(text)}</w:t></w:r></w:p>"
    )


def build_document_xml() -> str:
    body = "".join(paragraph(style, text) for style, text in PARAS)
    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        f"<w:body>{body}<w:sectPr/></w:body></w:document>"
    )


CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
"""

RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
"""

DOC_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>
"""


def write_docx(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", CONTENT_TYPES)
        zf.writestr("_rels/.rels", RELS)
        zf.writestr("word/document.xml", build_document_xml())
        zf.writestr("word/_rels/document.xml.rels", DOC_RELS)
    print(f"Wrote {path}")


if __name__ == "__main__":
    write_docx(OUT)
    write_docx(OUT_SERVER)
