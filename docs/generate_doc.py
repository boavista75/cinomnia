#!/usr/bin/env python3
"""Generate Cinomnia technical documentation in Serbian (DOCX)."""

import zipfile
from datetime import date
from xml.sax.saxutils import escape

OUTPUT = "/opt/lampp/htdocs/cinomnia/docs/Cinomnia_Tehnicka_Dokumentacija.docx"

W_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
R_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
PKG_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
CT_NS = "http://schemas.openxmlformats.org/package/2006/content-types"

TODAY = date.today().strftime("%d.%m.%Y.")


def p(text: str, style: str | None = None, bold: bool = False) -> str:
    text = escape(text)
    if bold:
        text = f'<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">{text}</w:t></w:r>'
    else:
        text = f'<w:r><w:t xml:space="preserve">{text}</w:t></w:r>'
    ppr = f'<w:pPr><w:pStyle w:val="{style}"/></w:pPr>' if style else ""
    return f"<w:p>{ppr}{text}</w:p>"


def p_mixed(parts: list[tuple[str, bool]]) -> str:
  """Paragraph with mixed bold/normal runs."""
  runs = ""
  for text, bold in parts:
    t = escape(text)
    if bold:
      runs += f'<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">{t}</w:t></w:r>'
    else:
      runs += f'<w:r><w:t xml:space="preserve">{t}</w:t></w:r>'
  return f"<w:p>{runs}</w:p>"


def bullet(text: str, level: int = 0) -> str:
    text = escape(text)
    ilvl = f'<w:ilvl w:val="{level}"/>'
    return (
        f"<w:p><w:pPr><w:pStyle w:val=\"ListParagraph\"/>"
        f"<w:numPr><w:ilvl w:val=\"{level}\"/><w:numId w:val=\"1\"/></w:numPr></w:pPr>"
        f"<w:r><w:t xml:space=\"preserve\">{text}</w:t></w:r></w:p>"
    )


def table(headers: list[str], rows: list[list[str]]) -> str:
    def cell(text: str, header: bool = False) -> str:
        t = escape(text)
        shd = '<w:shd w:val="clear" w:color="auto" w:fill="D9E2F3"/>' if header else ""
        return (
            f"<w:tc><w:tcPr><w:tcW w:w=\"2400\" w:type=\"dxa\"/>{shd}</w:tcPr>"
            f"<w:p><w:r><w:rPr>{'<w:b/>' if header else ''}</w:rPr>"
            f"<w:t xml:space=\"preserve\">{t}</w:t></w:r></w:p></w:tc>"
        )

    tbl = (
        '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/>'
        '<w:tblBorders>'
        '<w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        '<w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        '<w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
        "</w:tblBorders></w:tblPr>"
    )
    tbl += "<w:tr>" + "".join(cell(h, True) for h in headers) + "</w:tr>"
    for row in rows:
        tbl += "<w:tr>" + "".join(cell(c) for c in row) + "</w:tr>"
    return tbl + "</w:tbl>"


def build_document_xml() -> str:
    body_parts: list[str] = []

    def add(*parts):
        body_parts.extend(parts)

    # Title page
    add(
        p("CINOMNIA", "Title"),
        p("Kompletna tehnička dokumentacija", "Subtitle"),
        p(f"Verzija dokumenta: 1.0 | Datum: {TODAY}"),
        p(""),
    )

    # 1. Uvod
    add(p("1. Uvod", "Heading1"))
    add(p(
        "Cinomnia je veb aplikacija za otkrivanje, pretraživanje i upravljanje filmovima "
        "i TV serijama. Aplikacija koristi The Movie Database (TMDB) API kao izvor podataka "
        "o naslovima, dok lokalna MySQL baza čuva korisničke naloge, prilagođene liste, "
        "ocene, komentare i administrativne podatke."
    ))
    add(p(
        "Projekat je razvijen u PHP-u (bez frejmvorka) sa klasičnom arhitekturom "
        "server-side rendering (SSR) za stranice i JSON API krajnje tačke za asinhronu "
        "interakciju u pregledaču. Frontend koristi čist HTML, CSS i JavaScript bez "
        "build alata ili npm zavisnosti."
    ))

    # 2. Ciljevi
    add(p("2. Ciljevi i funkcionalnosti", "Heading1"))
    add(p("Glavni ciljevi sistema:", bold=True))
    for item in [
        "Pregled trendova, popularnih naslova i napredno filtriranje filmova i serija",
        "Pretraga naslova putem TMDB multi-search API-ja",
        "Detaljan prikaz naslova sa trejlerom, glumcima, sezonama i franšizama",
        "Registracija, prijava i bezbedno upravljanje sesijom",
        "Korisničke prilagođene liste (watchlist) sa TMDB stavkama",
        "Lokalne ocene (1–10) i oznaka „gledano“",
        "Komentari sa jednonivojskim odgovorima i reakcijama (like/dislike)",
        "Administratorski panel za moderaciju korisnika i komentara",
    ]:
        add(bullet(item))

    # 3. Tehnologije
    add(p("3. Tehnološki stack", "Heading1"))
    add(table(
        ["Sloj", "Tehnologija", "Verzija / napomena"],
        [
            ["Backend", "PHP", "8.0+ (strict_types, readonly patterns)"],
            ["Baza podataka", "MySQL / MariaDB", "InnoDB, utf8mb4"],
            ["Web server", "Apache (LAMPP/XAMPP)", "mod_rewrite"],
            ["Spoljni API", "TMDB REST API v3", "cURL"],
            ["Frontend", "HTML5, CSS3, JavaScript", "Vanilla ES6+"],
            ["ORM", "—", "PDO sa prepared statements"],
            ["Autentifikacija", "PHP sesije + password_hash", "bcrypt/Argon2id"],
        ],
    ))
    add(p(""))

    # 4. Arhitektura
    add(p("4. Arhitektura sistema", "Heading1"))
    add(p("4.1. Pregled slojeva", "Heading2"))
    add(p(
        "Aplikacija prati jednostavnu slojevitu arhitekturu: prezentacioni sloj (PHP "
        "stranice + includes), servisni sloj (namespace Cinomnia\\), sloj pristupa "
        "podacima (PDO singleton) i spoljni TMDB klijent."
    ))
    add(p("Tok zahteva (tipičan GET):", bold=True))
    for step in [
        "Apache prima HTTP zahtev i prosleđuje ga odgovarajućem .php fajlu",
        "bootstrap.php učitava config, registruje autoloader i inicijalizuje sesiju",
        "Stranica koristi deljene servis instance ($tmdb, $auth, $customLists, itd.)",
        "TMDB podaci se dohvataju putem TMDB_Service; korisnički podaci iz MySQL",
        "includes/header.php i footer.php renderuju HTML odgovor",
    ]:
        add(bullet(step))

    add(p("4.2. Struktura direktorijuma", "Heading2"))
    add(table(
        ["Putanja", "Opis"],
        [
            ["config/", "Globalna konfiguracija (baza, TMDB, sesija) — blokirana .htaccess-om"],
            ["database/", "SQL skripta za kreiranje šeme"],
            ["includes/", "bootstrap.php, header, footer, navbar — blokirano"],
            ["src/", "PHP klase (Auth, Database, Security, Services)"],
            ["css/, js/, assets/", "Statički resursi"],
            ["api/", "Alternativni API put (user-actions duplikat)"],
            ["*.php (koren)", "Ulazne tačke: index, details, lists, admin, login..."],
        ],
    ))
    add(p(""))

    add(p("4.3. Namespace i autoloading", "Heading2"))
    add(p(
        "PSR-4 stil autoloader mapira Cinomnia\\ na src/ direktorijum. "
        "Primer: Cinomnia\\Auth\\AuthService → src/Auth/AuthService.php."
    ))

    # 5. Konfiguracija
    add(p("5. Konfiguracija i instalacija", "Heading1"))
    add(p("5.1. Preduslovi", "Heading2"))
    for item in [
        "LAMPP/XAMPP ili ekvivalent (Apache + PHP + MySQL)",
        "PHP ekstenzije: curl, pdo_mysql, json, mbstring, session",
        "TMDB API ključ (besplatan na developer.themoviedb.org)",
        "Kreirana prazna MySQL baza pod imenom cinomnia",
    ]:
        add(bullet(item))

    add(p("5.2. Koraci instalacije", "Heading2"))
    for i, step in enumerate([
        "Kopirati projekat u htdocs/cinomnia (ili odgovarajući DocumentRoot)",
        "U phpMyAdmin kreirati bazu cinomnia",
        "Pokrenuti database/setup.sql (phpMyAdmin SQL tab ili mysql CLI)",
        "U config/config.php podesiti DB_HOST, DB_USER, DB_PASS i TMDB_API_KEY",
        "Otvoriti http://localhost/cinomnia/index.php u pregledaču",
        "Za admin pristup: UPDATE users SET is_admin = 1 WHERE username = '...'",
    ], 1):
        add(bullet(f"{i}. {step}"))

    add(p("5.3. Konfiguracioni parametri", "Heading2"))
    add(table(
        ["Konstanta", "Podrazumevana vrednost", "Opis"],
        [
            ["DB_HOST", "localhost", "MySQL host"],
            ["DB_NAME", "cinomnia", "Ime baze"],
            ["DB_USER / DB_PASS", "root / (prazno)", "LAMPP podrazumevano"],
            ["TMDB_API_KEY", "(korisnički)", "API ključ za TMDB"],
            ["SESSION_LIFETIME", "3600", "Trajanje sesije u sekundama"],
            ["SESSION_REGEN_INTERVAL", "300", "Regeneracija session ID"],
            ["BASE_URL", "auto", "Izračunato iz SCRIPT_NAME"],
        ],
    ))
    add(p(""))

    # 6. Baza
    add(p("6. Šema baze podataka", "Heading1"))
    add(p(
        "Baza koristi InnoDB sa utf8mb4_unicode_ci. Sve strane ključeve imaju ON DELETE CASCADE "
        "gde je to logično (brisanje korisnika briše njegove liste, ocene, komentare)."
    ))

    tables = [
        ("users", "Korisnički nalozi: username, email, password_hash, is_admin"),
        ("custom_lists", "Korisničke liste (uključujući sistemske „watched“ i „You Have Rated“)"),
        ("list_items", "Stavke u listama (tmdb_id, media_type, title, poster_path)"),
        ("user_ratings_history", "Ocene 1–10 i status gledano po naslovu"),
        ("local_ratings", "Zasebna tabela za agregaciju lokalnih ocena zajednice"),
        ("comments", "Komentari na naslove; parent_comment_id za odgovore (jedan nivo)"),
        ("comment_reactions", "Like (+1) / dislike (-1) po korisniku i komentaru"),
    ]
    add(table(["Tabela", "Opis"], tables))

    add(p("6.1. Relacije", "Heading2"))
    for rel in [
        "users 1:N custom_lists → list_items",
        "users 1:N user_ratings_history, local_ratings, comments, comment_reactions",
        "comments self-reference: parent_comment_id (samo jedan nivo dubine)",
    ]:
        add(bullet(rel))

    # 7. Bezbednost
    add(p("7. Bezbednost", "Heading1"))
    add(p("Klasa Security (src/Security/Security.php) centralizuje:", bold=True))
    for item in [
        "Hardened sesije: HttpOnly, SameSite=Lax, Secure na HTTPS, fingerprint User-Agent-a",
        "Periodična regeneracija session ID (session fixation mitigacija)",
        "CSRF tokeni u formama i X-CSRF-Token header za AJAX",
        "htmlspecialchars za XSS prevenciju pri renderovanju",
        "Apache .htaccess blokira direktan pristup config/, src/, database/, includes/",
    ]:
        add(bullet(item))

    add(p("Autentifikacija:", bold=True))
    for item in [
        "password_hash / password_verify sa PASSWORD_DEFAULT",
        "Generičke poruke pri neuspešnoj prijavi (sprečavanje enumeracije)",
        "Admin privilegije se uvek proveravaju u bazi (AdminService::isAdmin), ne samo iz sesije",
    ]:
        add(bullet(item))

    # 8. TMDB
    add(p("8. Integracija sa TMDB API-jem", "Heading1"))
    add(p(
        "TMDB_Service enkapsulira sve HTTP pozive prema api.themoviedb.org/3 putem cURL-a. "
        "Podržani endpointi uključuju trending, discover, search/multi, movie/{id}, tv/{id}, "
        "genre/list, collection/{id} i sezone serija."
    ))
    add(p("8.1. Strategija performansi", "Heading2"))
    add(p(
        "Za grid prikaz (index.php), list endpointi ne vraćaju runtime ili broj sezona. "
        "Servis koristi curl_multi za paralelno dohvatanje detalja svake stavke "
        "(enrichWithDetails), čime se latencija približava jednom zahtevu umesto N sekvencijalnih."
    ))
    add(p("8.2. Mapiranje filtera", "Heading2"))
    add(table(
        ["UI filter", "TMDB parametar"],
        [
            ["Trending", "/trending/{type}/week"],
            ["Highest / Lowest rated", "discover + sort_by=vote_average.desc/asc, vote_count.gte=50"],
            ["Žanr", "with_genres"],
            ["Godina", "primary_release_date / first_air_date opseg"],
            ["Ocena", "vote_average.gte / vote_average.lte"],
            ["Pretraga", "/search/multi (samo movie i tv)"],
        ],
    ))
    add(p(""))

    # 9. Stranice
    add(p("9. Korisnički interfejs — stranice", "Heading1"))
    pages = [
        ("index.php", "Početna: grid naslova, sidebar filteri, paginacija"),
        ("details.php", "Detalji naslova: trejler, ocena, liste, komentari, sezone TV, kolekcija filmova"),
        ("lists.php", "Upravljanje prilagođenim listama (zahteva prijavu)"),
        ("login.php / register.php", "Autentifikacija sa CSRF i redirect parametrom"),
        ("logout.php", "Uništavanje sesije"),
        ("admin.php", "Admin panel: korisnici, komentari, statistike (samo is_admin)"),
    ]
    add(table(["Fajl", "Funkcija"], pages))

    # 10. API
    add(p("10. API krajnje tačke", "Heading1"))
    add(p("Svi API endpointi prihvataju samo POST i vraćaju application/json.", bold=True))

    add(p("10.1. user-actions.php", "Heading2"))
    add(p("Zahteva prijavu i validan CSRF token. Akcije:"))
    actions_user = [
        "create_list, rename_list, delete_list, get_lists, get_list_items",
        "add_to_list, add_to_lists, create_list_and_add, remove_from_list",
        "set_rating, clear_rating, toggle_watched, set_watched",
        "get_interaction, get_history",
    ]
    for a in actions_user:
        add(bullet(a))

    add(p("10.2. comments-api.php", "Heading2"))
    for a in [
        "get_comments — javno čitanje",
        "post_comment — prijava + CSRF",
        "set_reaction — like/dislike, prijava + CSRF",
    ]:
        add(bullet(a))

    add(p("10.3. admin-actions.php", "Heading2"))
    for a in [
        "delete_user — brisanje korisnika (ne može se obrisati sam sebe)",
        "delete_comment — moderacija komentara",
        "Zahteva isAdmin iz baze + CSRF",
    ]:
        add(bullet(a))

    add(p("10.4. HTTP status kodovi", "Heading2"))
    add(table(
        ["Kod", "Značenje"],
        [
            ["200", "Uspeh"],
            ["400", "Nevalidan zahtev / nepoznata akcija"],
            ["401", "Nije prijavljen"],
            ["403", "CSRF ili nedostaju admin privilegije"],
            ["404", "Lista nije pronađena"],
            ["405", "Metoda nije POST"],
        ],
    ))
    add(p(""))

    # 11. Servisi
    add(p("11. Servisni sloj (src/)", "Heading1"))
    services = [
        ("Database", "PDO singleton, ERRMODE_EXCEPTION, native prepared statements"),
        ("TMDB_Service", "TMDB HTTP klijent i helperi za slike/formatiranje"),
        ("AuthService", "Registracija, login, logout, sesija"),
        ("CustomListService", "CRUD lista; sistemske liste watched i You Have Rated"),
        ("UserRatingsHistoryService", "Ocene i watched; sinhronizacija sa local_ratings i listama"),
        ("CommentService", "Threaded komentari, max 2000 znakova, jedan nivo odgovora"),
        ("AdminService", "Upravljanje korisnicima, brisanje komentara, pregled ocena"),
        ("Security", "Sesija, CSRF, XSS escape"),
    ]
    add(table(["Klasa", "Odgovornost"], services))

    add(p("11.1. Sistemske liste", "Heading2"))
    add(p(
        "CustomListService automatski održava listu „watched“ kada korisnik označi naslov "
        "kao gledan, i listu „You Have Rated“ kada postavi ocenu. Ove liste se ne mogu "
        "preimenovati niti ručno menjati stavke."
    ))

    # 12. Frontend
    add(p("12. Frontend resursi", "Heading1"))
    add(table(
        ["Fajl", "Namena"],
        [
            ["css/style.css", "Glavni layout, grid kartice, filteri, detalji"],
            ["css/navbar.css", "Navigacija"],
            ["css/admin.css", "Admin panel"],
            ["js/details.js", "Ocene, liste, watched, komentari na stranici detalja"],
            ["js/lists.js", "Interakcija na lists.php"],
            ["js/admin.js", "AJAX za admin-actions.php"],
            ["js/navbar.js", "Mobilni meni"],
            ["assets/no-poster.svg", "Placeholder kada TMDB nema poster"],
        ],
    ))
    add(p(""))

    # 13. Tokovi
    add(p("13. Ključni poslovni tokovi", "Heading1"))
    add(p("13.1. Registracija i prijava", "Heading2"))
    for step in [
        "Korisnik šalje POST sa csrf_token",
        "AuthService validira ulaz i proverava duplikate u bazi",
        "Lozinka se čuva kao password_hash; pri loginu session_regenerate_id",
        "Sesija čuva user_id, username, is_admin",
    ]:
        add(bullet(step))

    add(p("13.2. Ocenjivanje naslova", "Heading2"))
    for step in [
        "details.js šalje POST na user-actions.php (action=set_rating)",
        "UserRatingsHistoryService upsert u user_ratings_history",
        "Sinhronizacija u local_ratings i dodavanje u rated listu",
    ]:
        add(bullet(step))

    add(p("13.3. Komentar sa odgovorom", "Heading2"))
    for step in [
        "post_comment sa opcionim parent_comment_id",
        "Validacija: roditelj mora biti top-level komentar na istom naslovu",
        "getThreadedComments gradi stablo za prikaz",
    ]:
        add(bullet(step))

    # 14. Održavanje
    add(p("14. Održavanje i proširenje", "Heading1"))
    for item in [
        "Nove stranice: require bootstrap.php, koristiti Security::escape za izlaz",
        "Nove API akcije: dodati case u switch sa CSRF i auth proverama",
        "Migracije baze: dodati ALTER u setup.sql sa komentarima",
        "Produkcija: pomeriti TMDB_API_KEY van repozitorijuma; uključiti HTTPS za Secure kolačiće",
        "Rate limiting: trenutno nije implementiran — preporučeno za produkciju",
    ]:
        add(bullet(item))

    # 15. Poznata ograničenja
    add(p("15. Poznata ograničenja", "Heading1"))
    for item in [
        "Pretraga ne kombinuje dodatne filtere (ograničenje TMDB search API-ja)",
        "Komentari podržavaju samo jedan nivo odgovora",
        "Nema email verifikacije ni resetovanja lozinke",
        "API ključ je u config fajlu — za produkciju koristiti env varijable",
        "Paralelno obogaćivanje grid-a šalje do ~20 TMDB zahteva po stranici",
    ]:
        add(bullet(item))

    # 16. Rečnik
    add(p("16. Rečnik pojmova", "Heading1"))
    add(table(
        ["Pojam", "Objašnjenje"],
        [
            ["TMDB", "The Movie Database — spoljni katalog filmova i serija"],
            ["media_type", "movie ili tv — tip naslova u aplikaciji i bazi"],
            ["tmdb_id", "Jedinstveni identifikator naslova u TMDB sistemu"],
            ["CSRF", "Cross-Site Request Forgery — zaštita formi tokenom"],
            ["SSR", "Server-Side Rendering — HTML generiše PHP na serveru"],
        ],
    ))

    add(p(""))
    add(p(f"Dokument generisan automatski na osnovu izvornog koda projekta Cinomnia. {TODAY}", bold=True))

    body = "".join(body_parts)
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="{W_NS}" xmlns:r="{R_NS}">
  <w:body>
    {body}
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>"""


def build_styles_xml() -> str:
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="{W_NS}">
  <w:style w:type="paragraph" w:styleId="Title" w:default="0">
    <w:name w:val="Title"/>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    <w:rPr><w:sz w:val="56"/><w:b/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle">
    <w:name w:val="Subtitle"/>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    <w:rPr><w:sz w:val="28"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:pPr><w:spacing w:before="360" w:after="120"/></w:pPr>
    <w:rPr><w:sz w:val="32"/><w:b/><w:color w:val="2F5496"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:pPr><w:spacing w:before="240" w:after="80"/></w:pPr>
    <w:rPr><w:sz w:val="26"/><w:b/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph">
    <w:name w:val="List Paragraph"/>
    <w:pPr><w:ind w:left="720"/></w:pPr>
  </w:style>
</w:styles>"""


def build_numbering_xml() -> str:
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="{W_NS}">
  <w:abstractNum w:abstractNumId="0">
    <w:lvl w:ilvl="0">
      <w:start w:val="1"/>
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="•"/>
      <w:lvlJc w:val="left"/>
      <w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>
    </w:lvl>
    <w:lvl w:ilvl="1">
      <w:start w:val="1"/>
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="◦"/>
      <w:lvlJc w:val="left"/>
      <w:pPr><w:ind w:left="1440" w:hanging="360"/></w:pPr>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="1">
    <w:abstractNumId w:val="0"/>
  </w:num>
</w:numbering>"""


def main():
    content_types = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="{CT_NS}">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>"""

    rels = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="{PKG_NS}">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

    doc_rels = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="{PKG_NS}">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
</Relationships>"""

    with zipfile.ZipFile(OUTPUT, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", content_types)
        zf.writestr("_rels/.rels", rels)
        zf.writestr("word/document.xml", build_document_xml())
        zf.writestr("word/styles.xml", build_styles_xml())
        zf.writestr("word/numbering.xml", build_numbering_xml())
        zf.writestr("word/_rels/document.xml.rels", doc_rels)

    print(f"Created: {OUTPUT}")


if __name__ == "__main__":
    main()
