#!/usr/bin/env python3
"""Generate Cinomnia seminar paper (DOCX) per ITS uputstvo — Serbian Cyrillic."""

import zipfile
from xml.sax.saxutils import escape

OUTPUT = "/opt/lampp/htdocs/cinomnia/docs/CinomniaSeminarskiRadJovanovic.docx"

W_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
R_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
PKG_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
CT_NS = "http://schemas.openxmlformats.org/package/2006/content-types"

TITLE = "Cinomnia — напредна веб платформа за откривање, праћење и заједничко оцењивање филмова и ТВ серија"
STUDENT = "Никола Јовановић 294/25"
PROFESSOR = "проф. др Ђорђе Петровић"
SUBMIT_DATE = "30.6.2026."
HEADER_TEXT = f"{TITLE} | {STUDENT}"

# Margins in twips (1 cm ≈ 567 twips)
MAR_LEFT = 1418   # 2.5 cm
MAR_RIGHT = 851   # 1.5 cm
MAR_TOP = 1418    # 2.5 cm
MAR_BOTTOM = 1418 # 2.5 cm


def rpr(bold=False, size=20, font="Verdana", caps=False, italic=False) -> str:
    parts = [f'<w:rFonts w:ascii="{font}" w:hAnsi="{font}" w:cs="{font}"/>']
    if bold:
        parts.append("<w:b/>")
    if italic:
        parts.append("<w:i/>")
    if caps:
        parts.append("<w:caps/>")
    parts.append(f'<w:sz w:val="{size}"/>')
    parts.append(f'<w:szCs w:val="{size}"/>')
    return "<w:rPr>" + "".join(parts) + "</w:rPr>"


def ppr(justify=True, before=120, after=120, center=False, page_break_before=False) -> str:
    jc = "center" if center else "justify"
    pb = "<w:pageBreakBefore/>" if page_break_before else ""
    return (
        f"<w:pPr>{pb}<w:spacing w:before=\"{before}\" w:after=\"{after}\" "
        f'w:line="240" w:lineRule="auto"/><w:jc w:val="{jc}"/></w:pPr>'
    )


def para(text: str, *, bold=False, size=20, center=False, before=120, after=120,
         page_break=False, caps=False) -> str:
    t = escape(text)
    return (
        f"<w:p>{ppr(center=center, before=before, after=after, page_break_before=page_break)}"
        f"<w:r>{rpr(bold=bold, size=size, caps=caps)}"
        f'<w:t xml:space="preserve">{t}</w:t></w:r></w:p>'
    )


def heading1(text: str, page_break=False) -> str:
    return para(text.upper(), bold=True, size=24, before=240, after=120, page_break=page_break)


def heading2(text: str) -> str:
    return para(text.upper(), bold=True, size=20, before=180, after=120)


def body(text: str) -> str:
    return para(text, size=20)


def empty() -> str:
    return "<w:p><w:pPr><w:spacing w:before=\"120\" w:after=\"120\"/></w:pPr></w:p>"


def sect_pr(title_page=False) -> str:
    header = ""
    footer = ""
    if not title_page:
        header = '<w:headerReference w:type="default" r:id="rId3"/>'
        footer = '<w:footerReference w:type="default" r:id="rId4"/>'
    return f"""<w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="{MAR_TOP}" w:right="{MAR_RIGHT}" w:bottom="{MAR_BOTTOM}" w:left="{MAR_LEFT}"
               w:header="708" w:footer="708" w:gutter="0"/>
      <w:pgNumType w:start="1"/>
      {header}
      {footer}
    </w:sectPr>"""


def build_content() -> list[str]:
    parts: list[str] = []

    # --- Title page (section 1, no header) ---
    parts.append(para("ВИСОКА ШКОЛА СТРУКОВНИХ СТУДИЈА ЗА ИНФОРМАЦИОНЕ ТЕХНОЛОГИЈЕ",
                      bold=True, size=26, center=True, before=1600, after=240))
    parts.append(para("Напредне веб технологије", bold=True, size=28, center=True, after=360))
    parts.append(para("Семинарски рад", size=30, center=True, after=480))
    parts.append(para(TITLE, bold=True, size=36, center=True, after=800))
    parts.append(para(f"Предметни наставник: {PROFESSOR}", size=20, after=120))
    parts.append(para(f"Студент: {STUDENT}", size=20, after=120))
    parts.append(para(f"Датум предаје: {SUBMIT_DATE}", size=20, after=480))
    parts.append(para("Београд, јун 2026.", bold=True, size=20, center=True, after=240))
    parts.append(sect_pr(title_page=True))

    # --- Main content section ---
    parts.append(heading1("Садржај", page_break=True))
    for line in [
        "Резиме",
        "1. Увод",
        "2. Теоријски и практични оквир",
        "3. Архитектура и имплементација система Cinomnia",
        "4. Закључак",
        "5. Литература",
        "6. Коришћени интернет садржаји",
        "7. Прилози",
    ]:
        parts.append(body(line))
    parts.append(empty())

    parts.append(heading1("Резиме", page_break=True))
    parts.append(body(
        "Cinomnia је веб апликација развијена у оквиру курса „Напредне веб технологије“, "
        "намењена љубитељима филмова и телевизијских серија. Систем омогућава преглед, "
        "претраживање и филтрирање садржаја из спољне базе The Movie Database (TMDB), "
        "као и персонализовано управљање корисничким листама, индивидуалним оценама од 1 "
        "до 10, статусом „гледано“ и коментарима са одговорима и реакцијама. Backend је "
        "имплементиран у PHP 8+ без коришћења веб framework-а, са MySQL базом података "
        "и PDO слојем са припремљеним упитима. Frontend користи server-side rendering, "
        "HTML5, CSS3 и vanilla JavaScript. Интеграција са TMDB REST API v3 остварује се "
        "преко cURL библиотеке, укључујући паралелно дохватање детаља путем curl_multi. "
        "Безбедност обухвата сесијску аутентификацију, password_hash, CSRF и XSS заштиту. "
        "Рад описује архитектуру, шему базе, сервисне класе и практичне аспекте имплементације."
    ))
    parts.append(body(
        "Кључне речи: PHP, MySQL, PDO, REST API, TMDB, server-side rendering, веб безбедност, "
        "оцењивање филмова, прилагођене листе"
    ))

    parts.append(heading1("1. Увод", page_break=True))
    parts.append(body(
        "Савремени дигитални екосистем забаве карактерише експоненцијални раст броја "
        "филмова и серија доступних на стриминг платформама, у биоскопима и архивама. "
        "Корисницима је потребна централизована алатка која не само да приказује метаподатке "
        "о садржају, већ им омогућава да воде личну евиденцију гледања, граде колекције "
        "и деле мишљења са заједницом. Традиционални приступ заснован на ручном вођењу "
        "табела или коришћењу генеричких друштвених мрежа не задовољава специфичне потребе "
        "ентузијаста филмског и серијског садржаја [1]."
    ))
    parts.append(body(
        "Предмет овог семинарског рада је пројекат Cinomnia — напредна веб платформа за "
        "откривање, праћење и заједничко оцењивање филмова и ТВ серија. Апликација је "
        "развијена као индивидуални пројекат у оквиру предмета „Напредне веб технологије“ "
        "на Високој школи струковних студија за информационе технологије. Циљ пројекта "
        "био је да се демонстрира способност пројектовања и имплементације комплетног "
        "веб система користећи савремене, али не и нужно „тешке“ технологије: PHP без "
        "framework-а, релациону базу, спољне REST сервисе и класичан трослојни архитектурни "
        "модел [2]."
    ))
    parts.append(body(
        "Главни функционални захтеви обухватали су преглед и филтрирање филмова и серија, "
        "детаљне странице са трејлерима, регистрацију и пријаву корисника, креирање "
        "прилагођених листа, оцењивање на скали од 1 до 10, означавање садржаја као "
        "гледаног, коментаре са једнонивоским одговорима и реакцијама, као и администраторски "
        "панел за модерацију. Нефункционални захтеви укључивали су безбедност од SQL "
        "injection, XSS, CSRF и session hijacking напада, перформансе при комуникацији са "
        "спољним API-jem, responsive дизајн и одрживост кода кроз јасну поделу одговорности."
    ))
    parts.append(body(
        "Рад је структурисан тако да прво представи теоријски оквир коришћених технологија, "
        "затим детаљно опише архитектуру и имплементацију Cinomnia система, да на крају "
        "формулише закључке и препоруке за даљи развој. Практични део рада заснива се на "
        "анализи стварног изворног кода пројекта, шеме базе података и токова података између "
        "клијента, сервера и спољног TMDB сервиса."
    ))
    parts.append(heading1("2. Теоријски и практични оквир"))
    parts.append(heading2("2.1. PHP и server-side rendering"))
    parts.append(body(
        "PHP (Hypertext Preprocessor) представља server-side скриптни језик широко присутан "
        "у веб hosting окружењима [1]. У верзији 8 и новијим доноси значајна побољшања "
        "перформанси, строгу типизацију помоћу declare(strict_types=1), модерне конструкте "
        "попут match израза и побољшану стандардну библиотеку. Cinomnia користи PHP без "
        "Laravel, Symfony или сличних framework-а, што захтева ручну организацију кода, "
        "али пружа потпуну контролу над архитектуром и минималан overhead при извршавању "
        "сваког HTTP захтева."
    ))
    parts.append(body(
        "Server-side rendering (SSR) подразумева да PHP скрипта на серверу генерише комплетан "
        "HTML документ пре слања клијенту. За разлику од Single Page Application приступа "
        "са React или Vue, SSR у Cinomnia омогућава брже иницијално учитавање садржаја, "
        "једноставније индексирање од стране претраживача и мању зависност од JavaScript "
        "bundle-a. Динамичке интеракције попут оцењивања, управљања листама и коментарисања "
        "реализоване су кроз асинхрони fetch ка JSON API endpoint-ima, док статички делови "
        "страница остају рендеровани на серверу. Апликација се покреће у LAMPP/XAMPP "
        "окружењу — пакету који укључује Apache HTTP Server, MySQL, PHP и phpMyAdmin."
    ))
    parts.append(heading2("2.2. MySQL, InnoDB и utf8mb4"))
    parts.append(body(
        "MySQL и компатибилна MariaDB представљају релациони систем управљања базама "
        "података заснован на SQL језику [2]. Cinomnia користи базу cinomnia са InnoDB "
        "storage engine-om, који подржава трансакције, стране кључеве и row-level locking — "
        "кључне карактеристике за интегритет података у multi-user окружењу. Карактер сет "
        "utf8mb4 са колацијом utf8mb4_unicode_ci омогућава складиштење пуног Unicode опсега, "
        "укључујући emoji у коментарима корисника и међународна имена филмова."
    ))
    parts.append(body(
        "Релациони модел повезује ентитете корисника, прилагођених листа, ставки листа, "
        "историје оцена и статуса гледања, локалних оцена за агрегацију, коментара и реакција "
        "на коментаре. Страни кључеви са ON DELETE CASCADE гарантују да брисање корисника "
        "аутоматски уклања повезане записе, спречавајући неконзистентност у бази. Свака "
        "ставка у систему идентификована је паром (tmdb_id, media_type), где је media_type "
        "вредност movie или tv, што одражава модел TMDB сервиса."
    ))
    parts.append(body(
        "Нормализација података примењена је у мери која балансира интегритет и перформансе. "
        "Док се кориснички подаци чувају у нормализованим табелама са јасним релацијама, "
        "одређена денормализација присутна је у list_items и user_ratings_history где се "
        "чувају title и poster_path. Ова одлука пројектна је свесна: приказ листа и историје "
        "оцена не захтева додатни TMDB позив за сваку ставку, што значајно смањује латенцију "
        "и оптерећење спољног API-ja при честом коришћењу страница lists.php и профилних "
        "прегледа."
    ))

    parts.append(heading2("2.3. PDO и prepared statements"))
    parts.append(body(
        "PHP Data Objects апстрахује приступ бази и подржава prepared statements — механизам "
        "у ком се SQL упит компајлира једном, а параметри bind-ују одвојено [1]. Ово ефикасно "
        "спречава SQL injection нападе, јер кориснички унос никада није директно конкатенисан "
        "у упит. У Cinomnia пројекту класа Database имплементира Singleton образац: једна "
        "делјена PDO инстанца по HTTP захтеву. Конфигурација укључује PDO::ATTR_ERRMODE => "
        "PDO::ERRMODE_EXCEPTION за експлицитно бацање грешака, PDO::FETCH_ASSOC за асоцијативне "
        "низове и PDO::ATTR_EMULATE_PREPARES => false за native prepared statements на нивоу "
        "MySQL driver-a."
    ))
    parts.append(heading2("2.4. REST API и интеграција са TMDB"))
    parts.append(body(
        "Representational State Transfer архитектурни стил дефинише ресурсе идентификоване "
        "URL-овима, приступ преко HTTP метода и размену података у JSON формату [3]. "
        "The Movie Database API v3 пружа RESTful приступ метаподацима о филмовима, серијама, "
        "жанровима, трејлерима и сликама [5]. Cinomnia користи endpoint-е за trending садржај, "
        "discover са параметрима сортирања, жанра, године и оцене, multi-search за текстуалну "
        "претрагу, као и endpoint-е за детаље појединачних наслова. Аутентификација према "
        "TMDB-у остварује се API кључем прослеђеним као query параметар."
    ))
    parts.append(body(
        "Концептуално, TMDB представља извор истине за медијске метаподатке, док локална "
        "база чува извор истине за корисничке податке. Ова подела одговорности је уобичајена "
        "у микросервисној и API-first архитектури, иако је Cinomnia монолитна апликација. "
        "Предност овог приступа је што се ажурирања TMDB каталога аутоматски одражавају у "
        "апликацији без миграција или синхронизације локалне медијске базе. Недостатак је "
        "апсолутна зависност од доступности спољног сервиса, што је прихватљиво за "
        "академски пројекат, али захтева додатне механизме отпорности у продукционом окружењу."
    ))
    parts.append(body(
        "HTTP комуникација имплементирана је у TMDB_Service класе помоћу cURL библиотеке. "
        "За листу резултата где свака картица захтева додатне метаподатке попут runtime-а "
        "или броја сезона, коришћен је curl_multi за паралелно извршавање више GET захтева, "
        "чиме се латенција приближава трајању једног захтева уместо збира N секвенцијалних "
        "позива. Poster и backdrop URL-ови граде се помоћу константи TMDB_IMG_BASE и "
        "одговарајућих величина слика дефинисаних у конфигурацији пројекта."
    ))
    parts.append(body(
        "TMDB API враћа податке у JSON формату са стандардном пагинацијом где свака страница "
        "садржи до двадесет резултата. Cinomnia поштује ово ограничење и приказује навигацију "
        "између страница на index.php. При комуникацији са спољним сервисом дефинисан је "
        "timeout од петнаест секунди како би се избегло бесконачно чекање при проблемима "
        "мреже. Грешке при дохватању података хватају се try/catch блоком на нивоу странице "
        "и кориснику се приказује разумљива порука о недоступности садржаја."
    ))

    parts.append(heading2("2.5. Frontend технологије"))
    parts.append(body(
        "Frontend слој не користи npm, webpack, React нити Vue. HTML5 семантички елементи "
        "попут header, nav, main, section и article побољшавају приступачност и SEO. CSS3 "
        "обезбеђује responsive grid layout, flexbox навигацију, media queries за мобилне "
        "уређаје и модуларне stylesheet-ове style.css, navbar.css и admin.css. Vanilla "
        "JavaScript у фајловима details.js, lists.js, navbar.js и admin.js управља асинхроним "
        "позивима ка JSON API-ju, DOM манипулацијом и корисничким интеракцијама без спољне "
        "библиотеке [6]. Овај приход смањује величину клијентског кода и елиминише потребу "
        "за build pipeline-om."
    ))
    parts.append(body(
        "Fetch API коришћен је за све асинхроне HTTP захтеве са POST методом и "
        "application/json Content-Type заглављем [14]. Одговори се парсирају као JSON "
        "објекти и на основу success поља ажурира се кориснички интерфејс. Ова архитектура "
        "одваја презентацију од пословне логике: JavaScript не садржи SQL нити директне "
        "упите ка бази, већ комуницира искључиво са серверским API слојем који енкапсулира "
        "све провере аутентификације, ауторизације и валидације података."
    ))

    parts.append(heading2("2.6. Безбедност веб апликација"))
    parts.append(body(
        "Безбедност је третирана као cross-cutting concern кроз цео систем [4]. Против "
        "SQL injection напада примењени су PDO prepared statements. XSS напади се спречавају "
        "функцијом Security::escape() која користи htmlspecialchars са ENT_QUOTES и UTF-8 "
        "кодирањем. CSRF заштита реализована је токеном у PHP сесији који се проверава при "
        "сваком POST захтеву, укључујући AJAX позиве преко X-CSRF-Token заглавља. Session "
        "fixation се митигира session_regenerate_id() при пријави, док се session hijacking "
        "открива fingerprint-ом User-Agent заглавља и периодичном регенерацијом session ID-a "
        "на сваких 300 секунди. Лозинке се чувају помоћу password_hash() са PASSWORD_DEFAULT "
        "алгоритмом, а верификација се врши password_verify(). При неуспешној пријави користе "
        "се генеричке поруке грешке како би се спречила енумерација корисничких налога."
    ))
    parts.append(body(
        "Додатне мере безбедности укључују HttpOnly и SameSite=Lax атрибуте на session "
        "колачићима, Secure флаг при HTTPS вези, блокирање директног приступа осетљивим "
        "директоријумима преко .htaccess датотеке и валидацију redirect путања функцијом "
        "safeRedirectPath() како би се спречили open redirect напади. Администраторске "
        "привилегије увек се верификују у бази података, а не само из сесијских променљивих, "
        "што спречава ескалацију привилегија у случају манипулације сесијом."
    ))

    parts.append(heading1("3. Архитектура и имплементација система Cinomnia"))
    parts.append(heading2("3.1. Слојевита архитектура"))
    parts.append(body(
        "Cinomnia прати трослојни архитектурни модел са јасном поделом одговорности. "
        "Презентациони слој садржи PHP странице index.php, details.php, lists.php, "
        "login.php, register.php и admin.php, које укључују заједничке шаблоне header.php, "
        "navbar.php и footer.php. Свака страница учитава includes/bootstrap.php, парсира "
        "GET или POST параметре, позива сервисе и рендерује HTML. Апликациони слој обухвата "
        "сервисне класе у namespace-у Cinomnia\\ и три JSON API фајла: user-actions.php, "
        "comments-api.php и admin-actions.php. Перзистентни слој чини MySQL база дефинисана "
        "у database/setup.sql, док спољни слој представља TMDB REST API приступан искључиво "
        "кроз TMDB_Service."
    ))
    parts.append(body(
        "Ток типичног захтева за почетну страницу почиње отварањем index.php са параметрима "
        "типа медија и филтера. Bootstrap иницијализује сесију и инстанцира сервисе TMDB_Service, "
        "AuthService, CustomListService, UserRatingsHistoryService, CommentService и AdminService. "
        "Функција parseBrowseFilters() санитизује филтер параметре. TMDB_Service позива "
        "одговарајући endpoint — trending, discover или search — у зависности од стања "
        "филтера. Метода enrichWithDetails() паралелно допуњује runtime или број сезона за "
        "сваку картицу. PHP генерише HTML grid са постерима и метаподацима, а даље "
        "интеракције корисника иду ка JSON API-ju без потпуног преучитавања странице."
    ))
    parts.append(body(
        "Ова архитектура омогућава јасну одвојеност између читања јавних података и "
        "модификације корисничких података. GET захтеви за странице не мењају стање система "
        "и не захтевају аутентификацију за преглед садржаја, док POST захтеви ка API-ju "
        "увек захтевају пријаву и CSRF токен за операције које мењају податке. Овај принцип "
        "усаглашен је са REST препорукама о идемпотентности GET метода и коришћењу POST "
        "за акције које имају спољне ефекте на стање система [3]."
    ))
    parts.append(body(
        "Autoloading је имплементиран једноставним PSR-4-style callback-om у bootstrap.php: "
        "класа Cinomnia\\Auth\\AuthService мапира се на src/Auth/AuthService.php. Константа "
        "CINOMNIA_APP спречава директан приступ config.php преко веб сервера, док .htaccess "
        "додатно блокира приступ директоријумима config, src, database и includes."
    ))
    parts.append(body(
        "Конфигурација пројекта централизована је у config/config.php и обухвата параметре "
        "базе података, TMDB API кључ и base URL, величине слика за постере и backdrop-ове, "
        "као и параметре сесије укључујући SESSION_LIFETIME од 3600 секунди и "
        "SESSION_REGEN_INTERVAL од 300 секунди. BASE_URL се аутоматски израчунава из "
        "SCRIPT_NAME како би линкови и session cookie path били конзистентни и за странице "
        "и за API endpoint-е у поддиректоријуму api."
    ))

    parts.append(heading2("3.2. Шема базе података"))
    parts.append(body(
        "Табела users чува налоге корисника са колонама username, email, password_hash и "
        "is_admin за администраторске привилегије. Табела custom_lists омогућава корисницима "
        "креирање именованих колекција, укључујући аутоматски генерисане листе „Watched“ и "
        "„You Have Rated“ које CustomListService синхронизује са user_ratings_history. "
        "Табела list_items повезује листе са TMDB насловима и денормализовано чува title "
        "и poster_path ради бржег приказа без поновног TMDB позива."
    ))
    parts.append(body(
        "Табела user_ratings_history чува индивидуалне оцене од 1 до 10 и статус is_watched "
        "по пару корисник–наслов, са UNIQUE ограничењем на комбинацију user_id, tmdb_id и "
        "media_type. Табела local_ratings служи за агрегацију оцена заједнице и приказ "
        "просечне локалне оцене на страници детаља. Табела comments подржава thread структуру "
        "са parent_comment_id за једнонивоске одговоре, док comment_reactions чува like и "
        "dislike реакције по кориснику и коментару. Индекси на колонама user_id, tmdb_id "
        "и media_type убрзавају честе упите при приказу историје и коментара."
    ))
    parts.append(body(
        "CHECK constraint на колони rating у user_ratings_history гарантује да вредност буде "
        "NULL или у опсегу од 1 до 10, што представља додатни слој валидације поред "
        "апликационе провере у UserRatingsHistoryService. Временске ознаке rated_at и "
        "watched_at омогућавају хронолошки преглед активности корисника, док колоне "
        "created_at и updated_at прате животни циклус самог записа. Овај модел подржава "
        "сценарије где корисник прво означи наслов као гледан, а касније додели оцену, "
        "или обрнуто, без креирања дуплих редова у бази."
    ))

    parts.append(heading2("3.3. Аутентификација и JSON API"))
    parts.append(body(
        "Регистрација у AuthService валидира username regex-ом, email filter_var функцијом, "
        "минималну дужину лозинке од осам карактера и проверава дупликате пре INSERT-a. "
        "Пријава прихвата username или email, користи генеричку поруку грешке и након успеха "
        "поставља $_SESSION['user_id'], username и is_admin. Администраторске операције у "
        "AdminService поново проверавају is_admin флаг у бази, не ослањајући се искључиво "
        "на сесију, што представља defense in depth приступ."
    ))
    parts.append(body(
        "Странице login.php и register.php користе CSRF токен у скривеном пољу форме, "
        "док JSON API endpoint-и прихватају токен и у телу захтева и кроз X-CSRF-Token "
        "заглавље. Функција getRequestCsrfToken() у Security класи нормализује начин "
        "дохватања токена из различитих извора. Након успешне пријаве, корисник се може "
        "преусмерити на страницу са које је дошао, али само ако redirect параметар "
        "задовољава safeRedirectPath() проверу која дозвољава искључиво релативне путање "
        "без протокола, чиме се спречава open redirect напад."
    ))
    parts.append(body(
        "Три JSON API фајла служе као слој за асинхроне операције. user-actions.php захтева "
        "POST, аутентификацију и валидан CSRF token, а подржава акције за креирање, "
        "преименовање и брисање листа, додавање и уклањање ставки, постављање и брисање "
        "оцена, као и управљање watched статусом. comments-api.php омогућава јавно читање "
        "коментара, док пост и брисање захтевају пријаву и CSRF. admin-actions.php је "
        "резервисан за администраторе и обухвата брисање коментара, промоцију корисника "
        "и преглед статистика. Оцене корисника уписују се са ON DUPLICATE KEY UPDATE, што "
        "омогућава идемпотентно ажурирање постојећих записа."
    ))
    parts.append(body(
        "Мапирање UI филтера на TMDB параметре реализовано је у TMDB_Service::discover(). "
        "Када корисник бира сортирање по оцени, додаје се vote_count.gte = 50 како би се "
        "искључили наслови са једним гласом и непоузданим просеком. За филме се користе "
        "primary_release_date параметри, док се за серије користе first_air_date параметри. "
        "Текстуална претрага користи /search/multi endpoint и филтрира резултате на movie "
        "и tv типове, без примене додатних филтера због ограничења TMDB search API-ja."
    ))
    parts.append(body(
        "JSON API враћа стандартизоване одговоре са success пољем и одговарајућим HTTP "
        "статус кодовима. Код 401 сигнализира недостатак аутентификације, 403 неважећи CSRF "
        "token или недостатак администраторских привилегија, 404 непостојећи ресурс, 405 "
        "неисправну HTTP методу, док 200 означава успешну операцију. Ова конвенција олакшава "
        "обраду грешака на клијентској страни и усклађена је са REST праксама [3]."
    ))
    parts.append(body(
        "CommentService имплементира логику за threaded коментаре са ограничењем на један "
        "ниво одговора. Приликом постања одговора, сервис проверава да родитељски коментар "
        "припада истом наслову и да је top-level, односно да нема сопственог parent_comment_id. "
        "Максимална дужина коментара ограничена је на две хиљаде карактера. Реакције like "
        "и dislike чувају се у comment_reactions са UNIQUE ограничењем по пару корисник–коментар, "
        "што омогућава једну реакцију по кориснику са могућношћу промене типа реакције."
    ))

    parts.append(heading2("3.4. Кориснички интерфејс"))
    parts.append(body(
        "Почетна страница index.php приказује responsive grid картица са постером, насловом, "
        "годином, TMDB оценом, runtime-om или бројем сезона и епизода. Бочна трака садржи "
        "филтере за тип медија, сортирање, жанр, опсег година, TMDB оцену и поље за претрагу. "
        "Пагинација користи GET параметар page. Страница details.php приказује backdrop, "
        "синопсис, главну екипу, уграђени YouTube трејлер, TMDB оцену, локални просек оцена "
        "заједнице, контроле за оцену и watched статус, панел за додавање у листу и секцију "
        "коментара учитавану преко comments-api.php."
    ))
    parts.append(body(
        "Страница lists.php омогућава CRUD операције над прилагођеним листама и преглед "
        "ставки са навигацијом ка детаљима. Администраторски панел admin.php приказује "
        "статистике корисника, коментаре за модерацију и алатке за управљање улогама. "
        "Визуелни идентитет заснива се на палети боја #5C9EAD, #326273 и #E39774, са "
        "Inter фонтом и glass-accented UI елементима. Сви динамички делови HTML-a користе "
        "Security::escape() при испису username-a, наслова и текста коментара. JavaScript на "
        "страници детаља шаље POST захтеве са csrf_token у телу или X-CSRF-Token заглављу."
    ))
    parts.append(body(
        "Корисничко искуство дизајнирано је тако да подржи типичан ток рада љубитеља "
        "филмова: претраживање и филтрирање на почетној страници, детаљан преглед "
        "интересантног наслова, додавање у листу за касније гледање, оцењивање након "
        "гледања и коментарисање ради размене мишљења са другим корисницима. Аутоматске "
        "листе „Watched“ и „You Have Rated“ смањују административни терет корисника јер "
        "систем сам одржава конзистентност између оцена, watched статуса и садржаја листа."
    ))
    parts.append(body(
        "Сервисне класе прате Single Responsibility Principle: свака класа управља једном "
        "области пословне логике. CustomListService и UserRatingsHistoryService имају "
        "циркуларну зависност решену setter методом setRatingsHistoryService(), омогућавајући "
        "синхронизацију аутоматских листа. Security је статичка utility класа без стања, "
        "док је TMDB_Service stateless осим конфигурационих поља, што олакшава тестирање и "
        "евентуалну замену mock имплементације."
    ))
    parts.append(body(
        "Responsive дизајн постигнут је комбинацијом CSS Grid за приказ картица, Flexbox за "
        "навигацију и media queries које прилагођавају layout за мобилне уређаје. Navbar "
        "садржи collapsible мени за ужи екране, имплементиран у navbar.js. Страница детаља "
        "приказује сезоне и епизоде за ТВ серије, информације о колекцији за филмове из "
        "исте франшизе, као и листу главних глумаца и чланова екипе. Placeholder слика "
        "no-poster.svg приказује се када TMDB не врати poster_path за одређени наслов."
    ))
    parts.append(body(
        "Процес инсталације пројекта обухвата копирање фајлова у htdocs директоријум, "
        "креирање MySQL базе cinomnia, покретање database/setup.sql скрипте и подешавање "
        "TMDB API кључа у конфигурацији."
    ))

    parts.append(heading1("4. Закључак"))
    parts.append(body(
        "Пројекат Cinomnia демонстрира да је могуће изградити функционално богату веб "
        "апликацију за филмску заједницу користећи класичан LAMP stack без модерних "
        "JavaScript framework-а. Комбинација PHP 8 server-side rendering-a, MySQL релационог "
        "модела са строгим интегритетом, PDO prepared statements и пажљиво имплементиране "
        "безбедносне праксе чини систем погодним за продукционо окружење средњег обима."
    ))
    parts.append(body(
        "Интеграција са TMDB REST API-jem омогућила је приступ обимној бази метаподатака "
        "без сопственог складиштења медијског садржаја — Cinomnia складишти искључиво "
        "корисничке податке, док постери, синопсис и трејлери долазе у реалном времену. "
        "Паралелно дохватање преко curl_multi показало се као кључна оптимизација за "
        "корисничко искуство на листама са више картица. Имплементиране функционалности "
        "покривају комплетан животни циклус интеракције љубитеља филмова са платформом, "
        "а JSON API endpoint-и омогућавају постепену еволуцију ка богатијем frontend-у без "
        "потпуног преписивања backend-a."
    ))
    parts.append(body(
        "Ограничења тренутне верзије укључују зависност од доступности TMDB сервиса и "
        "интернета, недостатак serverskog кеширања одговора TMDB-a, session-based "
        "аутентификацију без подршке за мобилне native клијенте и једнонивоске одговоре "
        "на коментаре. Будући рад могао би обухватити Redis кеш, rate limiting, email "
        "верификацију, интернационализацију и проширену претрагу по глумцима и редитељима. "
        "У контексту курса „Напредне веб технологије“, пројекат потврђује важност разумевања "
        "HTTP протокола, REST принципа, релационог моделовања, сигурносних рањивости OWASP "
        "Top 10 и балансирања између једноставности архитектуре и функционалних захтева."
    ))
    parts.append(heading1("5. Литература"))
    for ref in [
        "[1] PHP Group. (2024). PHP manual: PDO and password hashing. https://www.php.net/manual/en/",
        "[2] Oracle Corporation. (2024). MySQL 8.0 reference manual. https://dev.mysql.com/doc/refman/8.0/en/",
        "[3] Fielding, R. T. (2000). Architectural styles and the design of network-based software architectures. University of California, Irvine.",
        "[4] OWASP Foundation. (2021). OWASP Top Ten Web Application Security Risks. https://owasp.org/www-project-top-ten/",
        "[5] The Movie Database. (2024). TMDB API documentation. https://developer.themoviedb.org/docs",
        "[6] W3C. (2017). HTML5: A vocabulary and associated APIs for HTML and XHTML. https://www.w3.org/TR/html52/",
    ]:
        parts.append(body(ref))

    parts.append(heading1("6. Коришћени интернет садржаји"))
    for ref in [
        "[7] https://www.php.net/manual/en/book.pdo.php (посећено 28.06.2026.)",
        "[8] https://dev.mysql.com/doc/refman/8.0/en/innodb-storage-engine.html (посећено 28.06.2026.)",
        "[9] https://developer.themoviedb.org/reference/intro/getting-started (посећено 29.06.2026.)",
        "[10] https://developer.themoviedb.org/reference/movie-details (посећено 29.06.2026.)",
        "[11] https://owasp.org/www-community/attacks/SQL_Injection (посећено 27.06.2026.)",
        "[12] https://owasp.org/www-community/attacks/xss/ (посећено 27.06.2026.)",
        "[13] https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html (посећено 27.06.2026.)",
        "[14] https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API (посећено 26.06.2026.)",
        "[15] https://www.php.net/manual/en/function.curl-multi-init.php (посећено 29.06.2026.)",
    ]:
        parts.append(body(ref))

    parts.append(heading1("7. Прилози"))
    parts.append(heading2("Прилог А — Скраћенице"))
    parts.append(body(
        "API означава Application Programming Interface, CSRF Cross-Site Request Forgery, "
        "CSS Cascading Style Sheets, HTML HyperText Markup Language, HTTP HyperText Transfer "
        "Protocol, JSON JavaScript Object Notation, LAMPP Linux Apache MySQL PHP Perl, "
        "OWASP Open Web Application Security Project, PDO PHP Data Objects, RDBMS Relational "
        "Database Management System, REST Representational State Transfer, SQL Structured "
        "Query Language, SSR Server-Side Rendering, TMDB The Movie Database, UI User "
        "Interface, URL Uniform Resource Locator и XSS Cross-Site Scripting."
    ))
    parts.append(heading2("Прилог Б — Сервисне класе"))
    parts.append(body(
        "Класа Database у namespace-у Cinomnia\\Database обезбеђује PDO Singleton конекцију. "
        "Класа Security у Cinomnia\\Security централизује сесију, CSRF и XSS escape. "
        "TMDB_Service у Cinomnia\\Services имплементира TMDB REST клијент са cURL и curl_multi "
        "подршком. AuthService управља регистрацијом, пријавом и одјавом. CustomListService "
        "обухвата CRUD прилагођених листа и ставки. UserRatingsHistoryService чува оцене "
        "и watched статус. CommentService управља коментарима, одговорима и реакцијама. "
        "AdminService пружа администраторску модерацију и статистике."
    ))

    parts.append(sect_pr(title_page=False))
    return parts


def build_document_xml() -> str:
    body = "".join(build_content())
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="{W_NS}" xmlns:r="{R_NS}">
  <w:body>
    {body}
  </w:body>
</w:document>"""


def build_styles_xml() -> str:
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="{W_NS}">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Verdana" w:hAnsi="Verdana" w:cs="Verdana"/>
        <w:sz w:val="20"/><w:szCs w:val="20"/>
        <w:lang w:val="sr-RS" w:eastAsia="sr-RS" w:bidi="ar-SA"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:after="120" w:line="240" w:lineRule="auto"/>
        <w:jc w:val="both"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>"""


def build_header_xml() -> str:
    t = escape(HEADER_TEXT)
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:hdr xmlns:w="{W_NS}">
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="auto"/></w:pBdr></w:pPr>
    <w:r>{rpr(size=20)}<w:t xml:space="preserve">{t}</w:t></w:r>
  </w:p>
</w:hdr>"""


def build_footer_xml() -> str:
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="{W_NS}">
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="auto"/></w:pBdr></w:pPr>
    <w:r>{rpr(size=20)}<w:t xml:space="preserve">Страна </w:t></w:r>
    <w:fldSimple w:instr=" PAGE ">
      <w:r>{rpr(size=20)}<w:t>1</w:t></w:r>
    </w:fldSimple>
    <w:r>{rpr(size=20)}<w:t xml:space="preserve"> / </w:t></w:r>
    <w:fldSimple w:instr=" NUMPAGES ">
      <w:r>{rpr(size=20)}<w:t>10</w:t></w:r>
    </w:fldSimple>
  </w:p>
</w:ftr>"""


def main():
    content_types = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="{CT_NS}">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
</Types>"""

    rels = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="{PKG_NS}">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

    doc_rels = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="{PKG_NS}">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
</Relationships>"""

    with zipfile.ZipFile(OUTPUT, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", content_types)
        zf.writestr("_rels/.rels", rels)
        zf.writestr("word/document.xml", build_document_xml())
        zf.writestr("word/styles.xml", build_styles_xml())
        zf.writestr("word/header1.xml", build_header_xml())
        zf.writestr("word/footer1.xml", build_footer_xml())
        zf.writestr("word/_rels/document.xml.rels", doc_rels)

    print(f"Created: {OUTPUT}")


if __name__ == "__main__":
    main()
