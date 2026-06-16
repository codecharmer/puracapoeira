/* ================================================================
   PURA CAPOEIRA — main.js
   Vanilla JS · Header/Footer include · Dynamic data loading
   ================================================================ */

(function () {
  "use strict";

  const SUPPORTED_LANGS = ["es", "en", "pt"];
  const DEFAULT_LANG = "es";

  function normalizeLang(value) {
    const v = String(value || "").toLowerCase();
    if (v.startsWith("en")) return "en";
    if (v.startsWith("pt")) return "pt";
    return "es";
  }

  function getPreferredLang() {
    const saved = localStorage.getItem("pc_lang");
    if (saved && SUPPORTED_LANGS.includes(saved)) return saved;
    const fromQuery = new URLSearchParams(window.location.search).get("lang");
    if (fromQuery && SUPPORTED_LANGS.includes(fromQuery)) return fromQuery;
    return normalizeLang(navigator.language || DEFAULT_LANG);
  }

  let currentLang = getPreferredLang();

  const I18N = {
    nav: {
      es: ["Inicio", "El Grupo", "Profesores", "Sedes", "Galeria", "Eventos", "Contacto"],
      en: ["Home", "The Group", "Professors", "Locations", "Gallery", "Events", "Contact"],
      pt: ["Inicio", "O Grupo", "Professores", "Sedes", "Galeria", "Eventos", "Contato"]
    },
    footer: {
      es: {
        aboutTitle: "Pura Capoeira",
        aboutText: "Escuela y comunidad internacional de capoeira. Cultura afrobrasilena, musica, disciplina y movimiento conectando comunidades en Mexico, Brasil, Angola y Estados Unidos.",
        navTitle: "Navegacion",
        sedesTitle: "Sedes",
        rights: "Todos los derechos reservados.",
        motto: "Capoeira · Cultura · Comunidade"
      },
      en: {
        aboutTitle: "Pura Capoeira",
        aboutText: "International capoeira school and community. Afro-Brazilian culture, music, discipline, and movement connecting communities in Mexico, Brazil, Angola, and the United States.",
        navTitle: "Navigation",
        sedesTitle: "Locations",
        rights: "All rights reserved.",
        motto: "Capoeira · Culture · Community"
      },
      pt: {
        aboutTitle: "Pura Capoeira",
        aboutText: "Escola e comunidade internacional de capoeira. Cultura afro-brasileira, musica, disciplina e movimento conectando comunidades no Mexico, Brasil, Angola e Estados Unidos.",
        navTitle: "Navegacao",
        sedesTitle: "Sedes",
        rights: "Todos os direitos reservados.",
        motto: "Capoeira · Cultura · Comunidade"
      }
    },
    common: {
      es: {
        loadingSedes: "Cargando sedes...",
        loadingProfs: "Cargando profesores...",
        loadingGallery: "Cargando galeria...",
        loadingEvents: "Cargando eventos...",
        loadingContacts: "Cargando contactos...",
        fallbackSedes: "No fue posible cargar las sedes.",
        fallbackProfs: "No fue posible cargar los profesores.",
        fallbackGallery: "Nuestra galeria completa estara disponible proximamente.",
        fallbackEvents: "Proximamente anunciaremos nuevas rodas, talleres y encuentros de Pura Capoeira.",
        fallbackContacts: "No fue posible cargar la informacion de contacto.",
        seeLocation: "Ver sede",
        seeProfile: "Ver perfil",
        next: "Proximo",
        moreInfo: "Mas info",
        whatsapp: "WhatsApp",
        instagram: "Instagram",
        facebook: "Facebook",
        website: "Sitio web",
        youtube: "YouTube",
        pendingLink: "Enlace por confirmar",
        contactWhatsApp: "Contactar por WhatsApp",
        generalContact: "Contacto general",
        backToLocations: "Volver a sedes",
        schedules: "Horarios",
        pricing: "Costos",
        contact: "Contacto",
        responsibleLabel: "Responsable",
        addressLabel: "Direccion",
        pricesPending: "Costos por confirmar.",
        schedulesPending: "Horarios por confirmar. Contactanos para conocer la proxima programacion.",
        byLocation: "Por sede",
        localContacts: "Contactos locales",
        generalForm: "Formulario general",
        writeUs: "Escribenos",
        sendMessage: "Enviar mensaje",
        openMailClient: "Al enviar abriremos tu cliente de correo. Tambien puedes contactarnos directamente por WhatsApp desde la tarjeta de cada sede.",
        name: "Nombre",
        city: "Ciudad",
        phone: "Telefono o WhatsApp",
        message: "Mensaje",
        all: "Todos",
        allLocations: "Todas las sedes",
        mapTitle: "Mapa interactivo de sedes",
        mapHint: "Explora marcadores y acerca el mapa para encontrar la sede mas cercana.",
        mapUnavailable: "No fue posible cargar el mapa en este navegador.",
        mapNoCoords: "Aun no hay coordenadas disponibles para mostrar en el mapa."
      },
      en: {
        loadingSedes: "Loading locations...",
        loadingProfs: "Loading professors...",
        loadingGallery: "Loading gallery...",
        loadingEvents: "Loading events...",
        loadingContacts: "Loading contacts...",
        fallbackSedes: "Could not load locations.",
        fallbackProfs: "Could not load professors.",
        fallbackGallery: "Our full gallery will be available soon.",
        fallbackEvents: "We will announce new rodas, workshops, and Pura Capoeira gatherings soon.",
        fallbackContacts: "Could not load contact information.",
        seeLocation: "View location",
        seeProfile: "View profile",
        next: "Upcoming",
        moreInfo: "More info",
        whatsapp: "WhatsApp",
        instagram: "Instagram",
        facebook: "Facebook",
        website: "Website",
        youtube: "YouTube",
        pendingLink: "Link coming soon",
        contactWhatsApp: "Contact via WhatsApp",
        generalContact: "General contact",
        backToLocations: "Back to locations",
        schedules: "Schedules",
        pricing: "Pricing",
        contact: "Contact",
        responsibleLabel: "Lead",
        addressLabel: "Address",
        pricesPending: "Pricing to be confirmed.",
        schedulesPending: "Schedule to be confirmed. Contact us for upcoming times.",
        byLocation: "By location",
        localContacts: "Local contacts",
        generalForm: "General form",
        writeUs: "Write to us",
        sendMessage: "Send message",
        openMailClient: "Submitting will open your email client. You can also contact us directly via WhatsApp from each location card.",
        name: "Name",
        city: "City",
        phone: "Phone or WhatsApp",
        message: "Message",
        all: "All",
        allLocations: "All locations",
        mapTitle: "Interactive locations map",
        mapHint: "Explore markers and zoom in to find the nearest location.",
        mapUnavailable: "Could not load the map in this browser.",
        mapNoCoords: "There are no coordinates available to display on the map yet."
      },
      pt: {
        loadingSedes: "Carregando sedes...",
        loadingProfs: "Carregando professores...",
        loadingGallery: "Carregando galeria...",
        loadingEvents: "Carregando eventos...",
        loadingContacts: "Carregando contatos...",
        fallbackSedes: "Nao foi possivel carregar as sedes.",
        fallbackProfs: "Nao foi possivel carregar os professores.",
        fallbackGallery: "Nossa galeria completa estara disponivel em breve.",
        fallbackEvents: "Em breve anunciaremos novas rodas, oficinas e encontros da Pura Capoeira.",
        fallbackContacts: "Nao foi possivel carregar as informacoes de contato.",
        seeLocation: "Ver sede",
        seeProfile: "Ver perfil",
        next: "Proximo",
        moreInfo: "Mais info",
        whatsapp: "WhatsApp",
        instagram: "Instagram",
        facebook: "Facebook",
        website: "Site",
        youtube: "YouTube",
        pendingLink: "Link em breve",
        contactWhatsApp: "Contato por WhatsApp",
        generalContact: "Contato geral",
        backToLocations: "Voltar para sedes",
        schedules: "Horarios",
        pricing: "Valores",
        contact: "Contato",
        responsibleLabel: "Responsavel",
        addressLabel: "Endereco",
        pricesPending: "Valores a confirmar.",
        schedulesPending: "Horarios a confirmar. Fale conosco para a proxima programacao.",
        byLocation: "Por sede",
        localContacts: "Contatos locais",
        generalForm: "Formulario geral",
        writeUs: "Escreva para nos",
        sendMessage: "Enviar mensagem",
        openMailClient: "Ao enviar, abriremos seu cliente de email. Voce tambem pode contatar por WhatsApp em cada card de sede.",
        name: "Nome",
        city: "Cidade",
        phone: "Telefone ou WhatsApp",
        message: "Mensagem",
        all: "Todos",
        allLocations: "Todas as sedes",
        mapTitle: "Mapa interativo de sedes",
        mapHint: "Explore os marcadores e aproxime o mapa para encontrar a sede mais proxima.",
        mapUnavailable: "Nao foi possivel carregar o mapa neste navegador.",
        mapNoCoords: "Ainda nao ha coordenadas disponiveis para mostrar no mapa."
      }
    }
  };

  function t(scope, key) {
    const dict = I18N[scope] && I18N[scope][currentLang];
    if (!dict) return "";
    return dict[key] || "";
  }

  const VALUE_I18N = {
    en: {
      "México": "Mexico",
      "Brasil": "Brazil",
      "Estado de México": "State of Mexico",
      "Estados Unidos": "United States",
      "Ceará": "Ceara",
      "Ciudad por confirmar": "City to be confirmed",
      "Toluca, Edo. Mexico": "Toluca, State of Mexico",
      "Cuernavaca · México": "Cuernavaca · Mexico",
      "Toluca · México": "Toluca · Mexico",
      "Guanajuato · México": "Guanajuato · Mexico",
      "Ceará · Brasil": "Ceara · Brazil",
      "Austin · Estados Unidos": "Austin · United States",
      "Dirección por confirmar": "Address to be confirmed",
      "Responsable por confirmar": "Lead to be confirmed",
      "Contacto por confirmar": "Contact to be confirmed",
      "Núcleo principal de Pura Capoeira en México. Entrenamiento, roda, música y comunidad en el corazón de Morelos.": "Main Pura Capoeira hub in Mexico. Training, roda, music, and community in the heart of Morelos.",
      "Núcleo en formación. Próximamente anunciaremos horarios y responsable local.": "Hub under development. We will announce schedules and local lead soon.",
      "Sede en expansión. Información local por confirmar.": "Growing location. Local information to be confirmed.",
      "Raíz brasileña de Pura Capoeira. Sede en el nordeste, tierra de capoeira, samba y axé.": "Brazilian root of Pura Capoeira. Location in the northeast, land of capoeira, samba, and axe.",
      "Núcleo en África, conectando la capoeira con sus raíces ancestrales en el continente.": "Hub in Africa, connecting capoeira with its ancestral roots on the continent.",
      "Sede en Texas. Comunidad creciente de Pura Capoeira en Estados Unidos.": "Location in Texas. A growing Pura Capoeira community in the United States.",
      "Adultos": "Adults",
      "Martes y jueves": "Tuesday and Thursday",
      "Miércoles y viernes": "Wednesday and Friday",
      "Mensualidad": "Monthly fee",
      "Inscripción / Uniforme": "Registration / Uniform",
      "$1,150 MXN al mes": "$1,150 MXN per month",
      "Mestre Fundador": "Founding Mestre",
      "Profesor": "Professor",
      "Profesora": "Professor",
      "Instructora": "Instructor",
      "Fundador de Pura Capoeira Austin, dedicado a la formacion de alumnos y a la difusion de la capoeira como arte y estilo de vida.": "Founder of Pura Capoeira Austin, dedicated to student development and sharing capoeira as an art and way of life.",
      "Mestre de referencia en Fortaleza, Ceara. Su trabajo destaca por la disciplina tecnica y la formacion integral dentro de la roda.": "Reference mestre in Fortaleza, Ceara. His work stands out for technical discipline and integral training inside the roda.",
      "Mestre con trayectoria en Caucaia, Ceara, enfocado en fortalecer la tecnica, la musicalidad y la identidad cultural de la capoeira.": "Mestre with a trajectory in Caucaia, Ceara, focused on strengthening technique, musicality, and capoeira cultural identity.",
      "Responsable de la formacion en Guanajuato, con una trayectoria centrada en la tecnica aplicada, la constancia y el crecimiento del grupo local.": "Lead trainer in Guanajuato, with a trajectory centered on applied technique, consistency, and growth of the local group.",
      "Inicio en 2005 en la University of the Virgin Islands; ensena desde 2015 y hoy dirige Pura Capoeira Cuernavaca.": "Started in 2005 at the University of the Virgin Islands; teaching since 2015 and now leading Pura Capoeira Cuernavaca.",
      "Profesora con base en Toluca, enfocada en procesos pedagogicos, tecnica corporal y crecimiento constante de la comunidad local.": "Professor based in Toluca, focused on pedagogy, body technique, and steady growth of the local community.",
      "Instructora de Toluca comprometida con la base tecnica, la musicalidad y la integracion de nuevos alumnos en la roda.": "Toluca instructor committed to technical foundations, musicality, and integration of new students into the roda.",
      "Instructor en Toluca, enfocado en el desarrollo tecnico de base, el ritmo y la disciplina en entrenamiento.": "Instructor in Toluca focused on technical foundations, rhythm, and training discipline.",
      "Instructor de Toluca dedicado a la tecnica aplicada en juego, la resistencia fisica y la constancia de entrenamiento.": "Toluca instructor dedicated to applied game technique, physical endurance, and consistent training.",
      "Roda de Pura Capoeira Cuernavaca": "Pura Capoeira Cuernavaca Roda",
      "Taller de musicalidad y berimbau": "Musicality and berimbau workshop",
      "Encuentro internacional Pura Capoeira": "Pura Capoeira international gathering",
      "Roda abierta para alumnos y comunidad de Pura Capoeira. Berimbau, canto y jogo.": "Open roda for students and the Pura Capoeira community. Berimbau, singing, and jogo.",
      "Taller intensivo de toques, canto y fundamento musical de la roda.": "Intensive workshop on rhythms, singing, and roda musical foundations.",
      "Encuentro anual de sedes Pura Capoeira. Talleres, roda general, batizado y troca de cordas.": "Annual Pura Capoeira locations gathering. Workshops, general roda, batizado, and troca de cordas.",
      "Próximo": "Upcoming",
      "Por confirmar": "To be confirmed",
      "Sede por confirmar": "Location to be confirmed",
      "Roda de Capoeira en Cuernavaca": "Capoeira roda in Cuernavaca",
      "Entrenamiento de adultos": "Adult training",
      "Clase de Kids en Tlaltenango": "Kids class in Tlaltenango",
      "Toque de berimbau": "Berimbau rhythm",
      "Encuentro internacional de Pura Capoeira": "Pura Capoeira international gathering",
      "Batizado e Troca de Cordas": "Batizado and troca de cordas",
      "Treino físico y técnico": "Physical and technical training",
      "Clase abierta": "Open class",
      "Momentos de jogo, música y energía de nuestra comunidad.": "Moments of jogo, music, and energy from our community.",
      "Secuencias de movimiento, defensa, esquiva, ritmo y fluidez.": "Movement sequences, defense, esquiva, rhythm, and flow.",
      "Niños y niñas aprenden coordinación, ritmo y respeto en el espacio de la roda.": "Girls and boys learn coordination, rhythm, and respect in the roda space.",
      "El berimbau dicta el ritmo del jogo. Sin música no hay roda.": "The berimbau sets the jogo rhythm. Without music there is no roda.",
      "Momentos de intercambio entre sedes, profesores y alumnos.": "Moments of exchange between locations, teachers, and students.",
      "Ceremonia tradicional donde alumnos reciben su primer apodo y cordel.": "Traditional ceremony where students receive their first nickname and cord.",
      "Fortalecimiento, condición y secuencias de movimiento.": "Strength work, conditioning, and movement sequences.",
      "Clase abierta a la comunidad. Bienvenidos visitantes.": "Open class for the community. Visitors are welcome.",
      "Clases": "Classes",
      "Música": "Music",
      "Entrenamiento": "Training",
      "Todas las sedes": "All locations"
    },
    pt: {
      "México": "Mexico",
      "Estado de México": "Estado do Mexico",
      "Estados Unidos": "Estados Unidos",
      "Ciudad por confirmar": "Cidade a confirmar",
      "Toluca, Edo. Mexico": "Toluca, Estado do Mexico",
      "Cuernavaca · México": "Cuernavaca · Mexico",
      "Toluca · México": "Toluca · Mexico",
      "Guanajuato · México": "Guanajuato · Mexico",
      "Ceará · Brasil": "Ceara · Brasil",
      "Dirección por confirmar": "Endereco a confirmar",
      "Responsable por confirmar": "Responsavel a confirmar",
      "Contacto por confirmar": "Contato a confirmar",
      "Núcleo principal de Pura Capoeira en México. Entrenamiento, roda, música y comunidad en el corazón de Morelos.": "Nucleo principal da Pura Capoeira no Mexico. Treino, roda, musica e comunidade no coracao de Morelos.",
      "Núcleo en formación. Próximamente anunciaremos horarios y responsable local.": "Nucleo em formacao. Em breve anunciaremos horarios e responsavel local.",
      "Sede en expansión. Información local por confirmar.": "Sede em expansao. Informacoes locais a confirmar.",
      "Raíz brasileña de Pura Capoeira. Sede en el nordeste, tierra de capoeira, samba y axé.": "Raiz brasileira da Pura Capoeira. Sede no nordeste, terra de capoeira, samba e axe.",
      "Núcleo en África, conectando la capoeira con sus raíces ancestrales en el continente.": "Nucleo na Africa, conectando a capoeira com suas raizes ancestrais no continente.",
      "Sede en Texas. Comunidad creciente de Pura Capoeira en Estados Unidos.": "Sede no Texas. Comunidade crescente da Pura Capoeira nos Estados Unidos.",
      "Adultos": "Adultos",
      "Martes y jueves": "Terca e quinta",
      "Miércoles y viernes": "Quarta e sexta",
      "Mensualidad": "Mensalidade",
      "Inscripción / Uniforme": "Inscricao / Uniforme",
      "$1,150 MXN al mes": "$1.150 MXN por mes",
      "Mestre Fundador": "Mestre Fundador",
      "Profesor": "Professor",
      "Profesora": "Professora",
      "Instructora": "Instrutora",
      "Instructor": "Instrutor",
      "Fundador de Pura Capoeira Austin, dedicado a la formacion de alumnos y a la difusion de la capoeira como arte y estilo de vida.": "Fundador da Pura Capoeira Austin, dedicado a formacao de alunos e a difusao da capoeira como arte e estilo de vida.",
      "Mestre de referencia en Fortaleza, Ceara. Su trabajo destaca por la disciplina tecnica y la formacion integral dentro de la roda.": "Mestre de referencia em Fortaleza, Ceara. Seu trabalho se destaca pela disciplina tecnica e formacao integral na roda.",
      "Mestre con trayectoria en Caucaia, Ceara, enfocado en fortalecer la tecnica, la musicalidad y la identidad cultural de la capoeira.": "Mestre com trajetoria em Caucaia, Ceara, focado em fortalecer tecnica, musicalidade e identidade cultural da capoeira.",
      "Responsable de la formacion en Guanajuato, con una trayectoria centrada en la tecnica aplicada, la constancia y el crecimiento del grupo local.": "Responsavel pela formacao em Guanajuato, com trajetoria centrada em tecnica aplicada, constancia e crescimento do grupo local.",
      "Inicio en 2005 en la University of the Virgin Islands; ensena desde 2015 y hoy dirige Pura Capoeira Cuernavaca.": "Iniciou em 2005 na University of the Virgin Islands; ensina desde 2015 e hoje dirige a Pura Capoeira Cuernavaca.",
      "Profesora con base en Toluca, enfocada en procesos pedagogicos, tecnica corporal y crecimiento constante de la comunidad local.": "Professora com base em Toluca, focada em processos pedagogicos, tecnica corporal e crescimento constante da comunidade local.",
      "Instructora de Toluca comprometida con la base tecnica, la musicalidad y la integracion de nuevos alumnos en la roda.": "Instrutora de Toluca comprometida com base tecnica, musicalidade e integracao de novos alunos na roda.",
      "Instructor en Toluca, enfocado en el desarrollo tecnico de base, el ritmo y la disciplina en entrenamiento.": "Instrutor em Toluca, focado no desenvolvimento tecnico de base, ritmo e disciplina no treino.",
      "Instructor de Toluca dedicado a la tecnica aplicada en juego, la resistencia fisica y la constancia de entrenamiento.": "Instrutor de Toluca dedicado a tecnica aplicada no jogo, resistencia fisica e constancia de treino.",
      "Roda de Pura Capoeira Cuernavaca": "Roda da Pura Capoeira Cuernavaca",
      "Taller de musicalidad y berimbau": "Oficina de musicalidade e berimbau",
      "Encuentro internacional Pura Capoeira": "Encontro internacional Pura Capoeira",
      "Roda abierta para alumnos y comunidad de Pura Capoeira. Berimbau, canto y jogo.": "Roda aberta para alunos e comunidade da Pura Capoeira. Berimbau, canto e jogo.",
      "Taller intensivo de toques, canto y fundamento musical de la roda.": "Oficina intensiva de toques, canto e fundamento musical da roda.",
      "Encuentro anual de sedes Pura Capoeira. Talleres, roda general, batizado y troca de cordas.": "Encontro anual das sedes Pura Capoeira. Oficinas, roda geral, batizado e troca de cordas.",
      "Próximo": "Proximo",
      "Por confirmar": "A confirmar",
      "Sede por confirmar": "Sede a confirmar",
      "Roda de Capoeira en Cuernavaca": "Roda de capoeira em Cuernavaca",
      "Entrenamiento de adultos": "Treino de adultos",
      "Clase de Kids en Tlaltenango": "Aula de kids em Tlaltenango",
      "Toque de berimbau": "Toque de berimbau",
      "Encuentro internacional de Pura Capoeira": "Encontro internacional da Pura Capoeira",
      "Batizado e Troca de Cordas": "Batizado e troca de cordas",
      "Treino físico y técnico": "Treino fisico e tecnico",
      "Clase abierta": "Aula aberta",
      "Momentos de jogo, música y energía de nuestra comunidad.": "Momentos de jogo, musica e energia da nossa comunidade.",
      "Secuencias de movimiento, defensa, esquiva, ritmo y fluidez.": "Sequencias de movimento, defesa, esquiva, ritmo e fluidez.",
      "Niños y niñas aprenden coordinación, ritmo y respeto en el espacio de la roda.": "Meninos e meninas aprendem coordenacao, ritmo e respeito no espaco da roda.",
      "El berimbau dicta el ritmo del jogo. Sin música no hay roda.": "O berimbau dita o ritmo do jogo. Sem musica nao ha roda.",
      "Momentos de intercambio entre sedes, profesores y alumnos.": "Momentos de intercambio entre sedes, professores e alunos.",
      "Ceremonia tradicional donde alumnos reciben su primer apodo y cordel.": "Cerimonia tradicional em que alunos recebem seu primeiro apelido e cordel.",
      "Fortalecimiento, condición y secuencias de movimiento.": "Fortalecimento, condicionamento e sequencias de movimento.",
      "Clase abierta a la comunidad. Bienvenidos visitantes.": "Aula aberta a comunidade. Visitantes bem-vindos.",
      "Clases": "Aulas",
      "Música": "Musica",
      "Entrenamiento": "Treino",
      "Todas las sedes": "Todas as sedes"
    }
  };

  function tv(value) {
    const str = String(value || "");
    if (currentLang === "es") return str;
    const dict = VALUE_I18N[currentLang] || {};
    return dict[str] || str;
  }

  /* ---------- Utilities ---------- */
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const isSedeDetailPage = /\/sedes\/[^/]+\.html$/.test(window.location.pathname);
  const basePrefix = isSedeDetailPage ? "../" : "./";
  const sedesMapState = {
    map: null,
    markersBySlug: new Map()
  };

  function localPath(path) {
    return basePrefix + String(path || "").replace(/^\.?\//, "").replace(/^\//, "");
  }

  function currentFileName() {
    const cleanPath = window.location.pathname.replace(/\/$/, "/index.html");
    const name = cleanPath.split("/").pop();
    return name || "index.html";
  }

  function escapeHTML(str = "") {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  async function loadJSON(path) {
    try {
      const res = await fetch(path, { cache: "no-store" });
      if (!res.ok) throw new Error("HTTP " + res.status);
      return await res.json();
    } catch (err) {
      console.error("loadJSON failed:", path, err);
      return null;
    }
  }

  /* ---------- Shared HEADER ---------- */
  const NAV_ITEMS = [
    { href: "index.html", key: 0, attr: "data-prefetch" },
    { href: "grupo.html", key: 1, attr: "data-prefetch" },
    { href: "profesores.html", key: 2, attr: "data-prefetch" },
    { href: "sedes.html", key: 3, attr: "data-prerender" },
    { href: "galeria.html", key: 4, attr: "data-prerender" },
    { href: "eventos.html", key: 5, attr: "data-prefetch" },
    { href: "contacto.html", key: 6, attr: "data-prefetch" }
  ];

  function navLabel(item) {
    const labels = I18N.nav[currentLang] || I18N.nav.es;
    return labels[item.key] || "";
  }

  function isActive(href) {
    const current = currentFileName();
    if (href === "index.html" && current === "index.html") return true;
    if (href === "sedes.html" && isSedeDetailPage) return true;
    if (href === current) return true;
    return false;
  }

  function renderHeader() {
    const host = $("[data-include='header']");
    if (!host) return;
    const navAria = currentLang === "en" ? "Main navigation" : currentLang === "pt" ? "Navegacao principal" : "Navegacion principal";
    const mobileNavAria = currentLang === "en" ? "Mobile navigation" : currentLang === "pt" ? "Navegacao movel" : "Navegacion movil";
    const menuAria = currentLang === "en" ? "Open menu" : currentLang === "pt" ? "Abrir menu" : "Abrir menu";
    const langAria = currentLang === "en" ? "Language switcher" : currentLang === "pt" ? "Seletor de idioma" : "Selector de idioma";
    const linksDesktop = NAV_ITEMS.map(item => (
      `<a href="${localPath(item.href)}" ${item.attr} class="${isActive(item.href) ? "active" : ""}" data-testid="nav-${navLabel(item).toLowerCase().replace(/\s/g, "-")}">${navLabel(item)}</a>`
    )).join("");
    const linksMobile = NAV_ITEMS.map(item => (
      `<a href="${localPath(item.href)}" ${item.attr} data-testid="mobile-nav-${navLabel(item).toLowerCase().replace(/\s/g, "-")}">${navLabel(item)}</a>`
    )).join("");

    const langButtons = [
      { code: "en", flag: "🇺🇸", label: "English" },
      { code: "es", flag: "🇲🇽", label: "Espanol" },
      { code: "pt", flag: "🇧🇷", label: "Portugues" }
    ].map(l => (
      `<button type="button" class="lang-btn ${currentLang === l.code ? "active" : ""}" data-lang="${l.code}" aria-label="${l.label}" title="${l.label}">${l.flag}</button>`
    )).join("");

    host.innerHTML = `
      <header class="site-header" role="banner">
        <div class="nav-inner">
          <a href="${localPath("index.html")}" class="brand" data-testid="brand-link" data-prefetch>
            <img src="${localPath("assets/images/logo-capoeira.png")}" alt="Logo Pura Capoeira" class="brand__logo" />
   
          </a>
          <nav class="site-nav" aria-label="${navAria}" data-testid="desktop-nav">
            ${linksDesktop}
          </nav>
          <div class="lang-switch" aria-label="${langAria}" role="group">${langButtons}</div>
          <button class="menu-toggle" aria-label="${menuAria}" aria-expanded="false" aria-controls="mobile-nav" data-testid="menu-toggle">
            <span></span><span></span><span></span>
          </button>
        </div>
        <nav id="mobile-nav" class="mobile-nav" aria-label="${mobileNavAria}" data-testid="mobile-nav">
          ${linksMobile}
        </nav>
      </header>
    `;

    const toggle = $(".menu-toggle", host);
    const mobileNav = $("#mobile-nav", host);
    const langBtns = $$(".lang-btn", host);
    toggle.addEventListener("click", () => {
      const expanded = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", String(!expanded));
      mobileNav.classList.toggle("open");
    });
    langBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        const next = btn.getAttribute("data-lang");
        if (!next || next === currentLang) return;
        currentLang = next;
        localStorage.setItem("pc_lang", currentLang);
        document.documentElement.setAttribute("lang", currentLang);
        init();
      });
    });
  }

  function renderFooter() {
    const host = $("[data-include='footer']");
    if (!host) return;
    const footerSedes = [
      { path: "sedes/cuernavaca.html", label: "Cuernavaca · México" },
      { path: "sedes/toluca.html", label: "Toluca · México" },
      { path: "sedes/guanajuato.html", label: "Guanajuato · México" },
      { path: "sedes/ceara.html", label: "Ceará · Brasil" },
      { path: "sedes/angola.html", label: "Angola" },
      { path: "sedes/austin.html", label: "Austin · Estados Unidos" }
    ];
    host.innerHTML = `
      <footer class="site-footer" role="contentinfo">
        <div class="container">
          <div class="footer-grid">
            <div class="footer-col footer-col--logo">
              <a class="footer-logo" href="${localPath("index.html")}" data-prefetch aria-label="Pura Capoeira">
                <img src="${localPath("assets/images/logo-capoeira.png")}" alt="Logo Pura Capoeira" class="footer-logo__img" />
              </a>
            </div>
            <div>
              <h4>${t("footer", "aboutTitle")}</h4>
              <p class="muted">${t("footer", "aboutText")}</p>
            </div>
            <div>
              <h4>${t("footer", "navTitle")}</h4>
              <ul>
                ${NAV_ITEMS.map(i => `<li><a href="${localPath(i.href)}" ${i.attr}>${navLabel(i)}</a></li>`).join("")}
              </ul>
            </div>
            <div>
              <h4>${t("footer", "sedesTitle")}</h4>
              <ul>
                ${footerSedes.map(item => `<li><a href="${localPath(item.path)}" data-prefetch>${escapeHTML(tv(item.label))}</a></li>`).join("")}
              </ul>
            </div>
          </div>

          <div class="footer-bottom">
            <span>© ${new Date().getFullYear()} Pura Capoeira. ${t("footer", "rights")}</span>
            <span>${t("footer", "motto")}</span>
          </div>
        </div>
      </footer>
    `;
  }

  /* ---------- Page renderers ---------- */
  function fmtCountryFlag(country) {
    const map = { "Mexico": "MX", "Brasil": "BR", "Angola": "AO", "Estados Unidos": "US", "México": "MX" };
    return map[country] || "";
  }

  function renderSedesGrid(target, sedes, opts = {}) {
    const limit = opts.limit || sedes.length;
    target.innerHTML = sedes.slice(0, limit).map(s => `
      <a class="sede-card" href="${escapeHTML(localPath(s.page))}" data-prefetch data-sede-slug="${escapeHTML(s.slug)}" data-testid="sede-card-${escapeHTML(s.slug)}">
        <div class="sede-card__img">
          <img src="${escapeHTML(s.image)}" alt="Pura Capoeira ${escapeHTML(tv(s.city))}" loading="lazy" />
        </div>
        <div class="sede-card__body">
          <span class="sede-card__country">${escapeHTML(tv(s.region))} · ${escapeHTML(tv(s.country))}</span>
          <h3 class="sede-card__city">${escapeHTML(tv(s.city))}</h3>
          <p class="sede-card__responsible">${escapeHTML(tv(s.responsible))}</p>
          <p class="sede-card__blurb">${escapeHTML(tv(s.blurb || ""))}</p>
          <span class="sede-card__link">${escapeHTML(t("common", "seeLocation"))}</span>
        </div>
      </a>
    `).join("");
  }

  async function initHomeSedes() {
    const host = $("#home-sedes");
    if (!host) return;
    const sedes = await loadJSON(localPath("data/sedes.json"));
    if (!sedes) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackSedes"))} <a href="${localPath("sedes.html")}">${escapeHTML(t("common", "seeLocation"))}</a>.</p>`;
      return;
    }
    renderSedesGrid(host, sedes);
  }

  async function initSedesPage() {
    const host = $("#sedes-grid");
    const hasMapPanel = Boolean($("#sedes-map"));
    if (!host && !hasMapPanel) return;
    const sedes = await loadJSON(localPath("data/sedes.json"));
    if (!sedes) {
      if (host) host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackSedes"))}</p>`;
      const mapEl = $("#sedes-map");
      if (mapEl) mapEl.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackSedes"))}</p>`;
      return;
    }
    if (host) renderSedesGrid(host, sedes);
    syncSedesMapHeadings();
    initSedesMap(sedes);
    bindSedesCardMapSync();
  }

  function syncSedesMapHeadings() {
    const title = $("#sedes-map-title");
    const hint = $("#sedes-map-hint");
    if (title) title.textContent = t("common", "mapTitle");
    if (hint) hint.textContent = t("common", "mapHint");
  }

  function resetSedesMap() {
    if (sedesMapState.map) {
      sedesMapState.map.remove();
      sedesMapState.map = null;
    }
    sedesMapState.markersBySlug.clear();
  }

  function setActiveSedeCard(slug) {
    $$(".sede-card--active").forEach(el => el.classList.remove("sede-card--active"));
    if (!slug) return;
    const card = $(`.sede-card[data-sede-slug="${slug}"]`);
    if (card) card.classList.add("sede-card--active");
  }

  function focusSedeMarker(slug, openPopup = false) {
    const marker = sedesMapState.markersBySlug.get(slug);
    if (!marker || !sedesMapState.map) return;
    sedesMapState.map.panTo(marker.getLatLng(), { animate: true, duration: 0.5 });
    if (openPopup) marker.openPopup();
    setActiveSedeCard(slug);
  }

  function buildSedePopupHTML(s) {
    const rows = [
      `<strong>${escapeHTML(tv(s.name))}</strong>`,
      `${escapeHTML(tv(s.city))} · ${escapeHTML(tv(s.country))}`,
      `${escapeHTML(t("common", "responsibleLabel"))}: ${escapeHTML(tv(s.responsible))}`,
      `<a href="${escapeHTML(localPath(s.page))}" data-prefetch>${escapeHTML(t("common", "seeLocation"))}</a>`
    ];

    if (s.whatsapp) {
      rows.splice(3, 0, `<a href="${escapeHTML(s.whatsapp)}" target="_blank" rel="noopener noreferrer">${escapeHTML(t("common", "whatsapp"))}</a>`);
    }

    return `<div class="sede-map-popup">${rows.join("<br />")}</div>`;
  }

  function initSedesMap(sedes) {
    const mapEl = $("#sedes-map");
    if (!mapEl) return;

    if (!window.L || typeof window.L.map !== "function") {
      mapEl.innerHTML = `<p class="fallback">${escapeHTML(t("common", "mapUnavailable"))}</p>`;
      return;
    }

    resetSedesMap();

    const map = window.L.map(mapEl, {
      scrollWheelZoom: false,
      zoomControl: true
    });

    window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    const bounds = window.L.latLngBounds();
    let markerCount = 0;

    sedes.forEach(s => {
      const lat = Number(s.lat);
      const lng = Number(s.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      const marker = window.L.marker([lat, lng]).addTo(map);
      marker.bindPopup(buildSedePopupHTML(s));
      marker.on("click", () => {
        setActiveSedeCard(s.slug);
      });

      sedesMapState.markersBySlug.set(s.slug, marker);
      bounds.extend([lat, lng]);
      markerCount += 1;
    });

    if (!markerCount) {
      map.setView([20, -30], 2);
      mapEl.insertAdjacentHTML("beforeend", `<p class="fallback" style="position:absolute;left:1rem;bottom:1rem;z-index:401;max-width:22rem;">${escapeHTML(t("common", "mapNoCoords"))}</p>`);
    } else {
      map.fitBounds(bounds, { padding: [36, 36] });
    }

    sedesMapState.map = map;

    window.setTimeout(() => {
      if (sedesMapState.map) sedesMapState.map.invalidateSize();
    }, 80);
  }

  function bindSedesCardMapSync() {
    const host = $("#sedes-grid");
    if (!host) return;
    $$(".sede-card", host).forEach(card => {
      const slug = card.getAttribute("data-sede-slug");
      if (!slug) return;
      card.addEventListener("mouseenter", () => focusSedeMarker(slug));
      card.addEventListener("focusin", () => focusSedeMarker(slug));
    });
  }

  async function initProfesoresPage() {
    const host = $("#prof-grid");
    if (!host) return;
    const profs = await loadJSON(localPath("data/profesores.json"));
    if (!profs) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackProfs"))}</p>`;
      return;
    }
    host.innerHTML = profs.map((p, i) => {
      const profileHref = p.profilePage ? localPath(p.profilePage) : "";
      const hasInstagram = Boolean(p.instagram);
      return `
      <article class="prof-card" data-testid="profesor-card-${i}">
        <div class="prof-card__img">
          <img src="${escapeHTML(p.image)}" alt="${escapeHTML(p.capoeiraName)}" loading="lazy" />
        </div>
        <div class="prof-card__body">
          <div class="prof-card__title">${escapeHTML(tv(p.title))} · ${escapeHTML(tv(p.city))}</div>
          <h3 class="prof-card__nick">${escapeHTML(p.capoeiraName)}</h3>
          <p class="prof-card__name">${escapeHTML(p.name)}</p>
          <p class="prof-card__bio">${escapeHTML(tv(p.bio))}</p>
          <div class="prof-card__meta">
            ${profileHref ? `<a href="${escapeHTML(profileHref)}" data-prefetch>${escapeHTML(t("common", "seeProfile"))} →</a>` : `<span>${escapeHTML(tv(p.country))}</span>`}
            <a href="${escapeHTML(localPath(p.locationPage))}" data-prefetch>${escapeHTML(t("common", "seeLocation"))}</a>
            ${hasInstagram ? `<a href="${escapeHTML(p.instagram)}" target="_blank" rel="noopener noreferrer">${escapeHTML(p.instagramHandle || "Instagram")}</a>` : ""}
          </div>
        </div>
      </article>
    `;
    }).join("");
  }

  /* ---------- Gallery ---------- */
  const GALLERY_CATEGORIES = ["Todos", "Clases", "Rodas", "Música", "Eventos", "Batizados", "Entrenamiento", "Kids", "Adultos"];
  const GALLERY_LOCATIONS = ["Todas las sedes", "Cuernavaca", "Toluca", "Guanajuato", "Ceará", "Angola", "Austin", "General"];

  function normToken(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  async function initGalleryPage() {
    const host = $("#gallery-grid");
    if (!host) return;
    const items = await loadJSON(localPath("data/gallery.json"));

    const catWrap = $("#filter-cats");
    const locWrap = $("#filter-locs");
    if (catWrap) {
      catWrap.innerHTML = GALLERY_CATEGORIES.map((c, i) =>
        `<button class="filter-pill" data-filter-cat="${escapeHTML(c)}" data-active="${i === 0}" data-testid="filter-cat-${escapeHTML(normToken(c).replace(/\s/g, '-'))}">${escapeHTML(c === "Todos" ? t("common", "all") : tv(c))}</button>`
      ).join("");
    }
    if (locWrap) {
      locWrap.innerHTML = GALLERY_LOCATIONS.map((c, i) =>
        `<button class="filter-pill" data-filter-loc="${escapeHTML(c)}" data-active="${i === 0}" data-testid="filter-loc-${escapeHTML(normToken(c).replace(/\s/g, '-'))}">${escapeHTML(c === "Todas las sedes" ? t("common", "allLocations") : tv(c))}</button>`
      ).join("");
    }

    if (!items) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackGallery"))}</p>`;
      return;
    }

    const state = { cat: "Todos", loc: "Todas las sedes" };

    function render() {
      const filtered = items.filter(it => {
        const matchCat = normToken(state.cat) === "todos" || normToken(it.category) === normToken(state.cat);
        const matchLoc = normToken(state.loc) === normToken("Todas las sedes") || normToken(it.location) === normToken(state.loc);
        return matchCat && matchLoc;
      });
      if (!filtered.length) {
        host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackGallery"))}</p>`;
        return;
      }
      host.innerHTML = filtered.map((it, i) => `
        <a class="gallery-item" href="${escapeHTML(it.url)}" target="_blank" rel="noopener noreferrer" data-testid="gallery-item-${i}">
          <img src="${escapeHTML(it.thumbnail)}" alt="${escapeHTML(tv(it.title))}" loading="lazy" />
          <div class="gallery-item__overlay">
            <span class="gallery-item__cat">${escapeHTML(tv(it.category))} · ${escapeHTML(tv(it.location))}</span>
            <h3 class="gallery-item__title">${escapeHTML(tv(it.title))}</h3>
            <span class="gallery-item__date">${escapeHTML(it.date)}</span>
          </div>
        </a>
      `).join("");
    }

    function bindPills(wrap, key, dataAttr) {
      if (!wrap) return;
      wrap.addEventListener("click", (e) => {
        const btn = e.target.closest(`button[${dataAttr}]`);
        if (!btn) return;
        $$(`button[${dataAttr}]`, wrap).forEach(b => b.setAttribute("data-active", "false"));
        btn.setAttribute("data-active", "true");
        state[key] = btn.getAttribute(dataAttr);
        render();
      });
    }
    bindPills(catWrap, "cat", "data-filter-cat");
    bindPills(locWrap, "loc", "data-filter-loc");

    render();
  }

  /* ---------- Events ---------- */
  function parseDate(iso) {
    if (!iso) return null;
    const d = new Date(iso + "T00:00:00");
    return isNaN(d) ? null : d;
  }
  function monthShort(d) {
    const locale = currentLang === "en" ? "en-US" : currentLang === "pt" ? "pt-BR" : "es-ES";
    return d.toLocaleString(locale, { month: "short" }).replace(".", "").toUpperCase();
  }

  async function initEventosPage() {
    const host = $("#event-list");
    if (!host) return;
    const items = await loadJSON(localPath("data/eventos.json"));
    if (!items || !items.length) {
      host.outerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackEvents"))}</p>`;
      return;
    }
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    host.innerHTML = items.map((ev, i) => {
      const d = parseDate(ev.date);
      const day = d ? d.getDate() : "—";
      const mon = d ? monthShort(d) : "";
      const past = d ? d.getTime() < today.getTime() : false;
      return `
        <div class="event-row" data-testid="event-row-${i}">
          <div class="event-row__date">
            <span class="day">${day}</span>
            <span class="month">${mon} ${d ? d.getFullYear() : ""}</span>
          </div>
          <div class="event-row__main">
            <h3>${escapeHTML(tv(ev.title))}</h3>
            <div class="event-row__meta">
              <span>${escapeHTML(ev.time || "")}</span>
              <span>${escapeHTML(tv(ev.location || ""))}</span>
              <span>${escapeHTML(tv(ev.venue || ""))}</span>
            </div>
            <p class="event-row__desc">${escapeHTML(tv(ev.description || ""))}</p>
          </div>
          <div>
            <span class="event-status ${past ? "event-status--past" : ""}">${escapeHTML(tv(ev.status || t("common", "next")))}</span>
            ${ev.url ? `<div style="margin-top:0.8rem;"><a class="btn btn--outline" href="${escapeHTML(ev.url)}" target="_blank" rel="noopener noreferrer" data-testid="event-cta-${i}">${escapeHTML(t("common", "moreInfo"))}</a></div>` : ""}
          </div>
        </div>
      `;
    }).join("");
  }

  /* ---------- Contact page cards ---------- */
  async function initContactCards() {
    const host = $("#contact-cards");
    if (!host) return;
    const sedes = await loadJSON(localPath("data/sedes.json"));
    if (!sedes) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackContacts"))}</p>`;
      return;
    }
    host.innerHTML = sedes.map((s, i) => `
      <article class="contact-card" data-testid="contact-card-${escapeHTML(s.slug)}">
        <span class="eyebrow">${escapeHTML(tv(s.country))}</span>
        <h3>${escapeHTML(tv(s.name))}</h3>
        <p class="contact-card__meta">${escapeHTML(tv(s.responsible))}</p>
        <p class="contact-card__meta">${escapeHTML(tv(s.address))}</p>
        <div class="contact-card__links">
          ${s.whatsapp ? `<a href="${escapeHTML(s.whatsapp)}" target="_blank" rel="noopener noreferrer" data-testid="contact-whatsapp-${escapeHTML(s.slug)}">${escapeHTML(t("common", "whatsapp"))}</a>` : `<span class="tag">${escapeHTML(t("common", "pendingLink"))}</span>`}
          ${s.instagram ? `<a href="${escapeHTML(s.instagram)}" target="_blank" rel="noopener noreferrer" data-testid="contact-instagram-${escapeHTML(s.slug)}">${escapeHTML(t("common", "instagram"))}</a>` : ""}
          ${s.facebook ? `<a href="https://www.facebook.com/search/top/?q=${encodeURIComponent(s.facebook)}" target="_blank" rel="noopener noreferrer" data-testid="contact-facebook-${escapeHTML(s.slug)}">${escapeHTML(t("common", "facebook"))}</a>` : ""}
          <a href="${escapeHTML(localPath(s.page))}" data-prefetch data-testid="contact-page-${escapeHTML(s.slug)}">${escapeHTML(t("common", "seeLocation"))}</a>
        </div>
      </article>
    `).join("");
  }

  /* ---------- mailto: form ---------- */
  function initContactForm() {
    const form = $("#contact-form");
    if (!form) return;
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = new FormData(form);
      const nombre = (data.get("nombre") || "").toString().trim();
      const ciudad = (data.get("ciudad") || "").toString().trim();
      const telefono = (data.get("telefono") || "").toString().trim();
      const mensaje = (data.get("mensaje") || "").toString().trim();
      const subject = `Pura Capoeira — ${nombre || t("common", "message")}`;
      const body = [
        `${t("common", "name")}: ${nombre}`,
        `${t("common", "city")}: ${ciudad}`,
        `${t("common", "phone")}: ${telefono}`,
        ``,
        `${t("common", "message")}:`,
        mensaje
      ].join("\n");
      // TODO: Reemplazar con dirección de correo real del grupo cuando esté disponible.
      const mailto = `mailto:contacto@puracapoeira.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      window.location.href = mailto;
    });
  }

  function initScrollReveal() {
    const blocks = $$(".reveal-on-scroll");
    const items = $$(".timeline-item");
    if (!blocks.length && !items.length) return;

    if (!("IntersectionObserver" in window)) {
      blocks.forEach(el => el.classList.add("is-visible"));
      items.forEach(el => el.classList.add("is-visible"));
      return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const target = entry.target;
        target.classList.add("is-visible");

        if (target.classList.contains("timeline")) {
          const delayStep = Number(target.getAttribute("data-reveal-delay")) || 80;
          $$(".timeline-item", target).forEach((item, index) => {
            window.setTimeout(() => item.classList.add("is-visible"), index * delayStep);
          });
        }

        obs.unobserve(target);
      });
    }, {
      threshold: 0.22,
      rootMargin: "0px 0px -8% 0px"
    });

    blocks.forEach(el => observer.observe(el));
  }

  const ORIGINAL_HTML_ATTR = "data-pc-original-html";
  const ORIGINAL_TEXT_ATTR = "data-pc-original-text";
  const ORIGINAL_FIRST_TEXT_ATTR = "data-pc-original-first-text";
  const ORIGINAL_ICON_TEXT_ATTR = "data-pc-original-icon-text";

  function markOriginalHTML(el) {
    if (!el.hasAttribute(ORIGINAL_HTML_ATTR)) {
      el.setAttribute(ORIGINAL_HTML_ATTR, el.innerHTML || "");
    }
  }

  function markOriginalText(el) {
    if (!el.hasAttribute(ORIGINAL_TEXT_ATTR)) {
      el.setAttribute(ORIGINAL_TEXT_ATTR, el.textContent || "");
    }
  }

  function markOriginalFirstTextNode(el) {
    if (!el || !el.firstChild || el.firstChild.nodeType !== Node.TEXT_NODE) return;
    if (!el.hasAttribute(ORIGINAL_FIRST_TEXT_ATTR)) {
      el.setAttribute(ORIGINAL_FIRST_TEXT_ATTR, el.firstChild.nodeValue || "");
    }
  }

  function markOriginalIconLabel(el, textNode) {
    if (!el || !textNode) return;
    if (!el.hasAttribute(ORIGINAL_ICON_TEXT_ATTR)) {
      el.setAttribute(ORIGINAL_ICON_TEXT_ATTR, textNode.nodeValue || "");
    }
  }

  function restoreOriginalStaticText() {
    $$(`[${ORIGINAL_HTML_ATTR}]`).forEach(el => {
      el.innerHTML = el.getAttribute(ORIGINAL_HTML_ATTR) || "";
    });

    $$(`[${ORIGINAL_TEXT_ATTR}]`).forEach(el => {
      el.textContent = el.getAttribute(ORIGINAL_TEXT_ATTR) || "";
    });

    $$(`[${ORIGINAL_FIRST_TEXT_ATTR}]`).forEach(el => {
      if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
        el.firstChild.nodeValue = el.getAttribute(ORIGINAL_FIRST_TEXT_ATTR) || "";
      }
    });

    $$(`[${ORIGINAL_ICON_TEXT_ATTR}]`).forEach(el => {
      const original = el.getAttribute(ORIGINAL_ICON_TEXT_ATTR) || "";
      const textNode = Array.from(el.childNodes).find(node => node.nodeType === Node.TEXT_NODE);
      if (textNode) {
        textNode.nodeValue = original;
      } else {
        el.appendChild(document.createTextNode(original));
      }
    });
  }

  function setHTML(sel, value) {
    if (!value) return;
    $$(sel).forEach(el => {
      markOriginalHTML(el);
      el.innerHTML = value;
    });
  }

  function setText(sel, value) {
    if (!value) return;
    $$(sel).forEach(el => {
      markOriginalText(el);
      el.textContent = value;
    });
  }

  function setFirstTextNode(sel, value) {
    if (!value) return;
    const el = $(sel);
    if (!el || !el.firstChild || el.firstChild.nodeType !== Node.TEXT_NODE) return;
    markOriginalFirstTextNode(el);
    el.firstChild.nodeValue = `${value} `;
  }

  function setIconLabel(sel, value) {
    if (!value) return;
    const el = $(sel);
    if (!el) return;
    const textNode = Array.from(el.childNodes).find(
      node => node.nodeType === Node.TEXT_NODE && node.nodeValue.trim().length
    );
    if (textNode) {
      markOriginalIconLabel(el, textNode);
      textNode.nodeValue = ` ${value}`;
      return;
    }
    const newNode = document.createTextNode(` ${value}`);
    markOriginalIconLabel(el, newNode);
    el.appendChild(newNode);
  }

  function applyExtraStaticTranslations(file) {
    const extras = {
      "index.html": {
        en: {
          ".hero__meta .eyebrow:nth-of-type(1)": "International school · established community",
          ".hero__meta .eyebrow--muted": "Mexico · Brazil · Angola · USA",
          ".hero .sub": "Capoeira, culture, music, and movement connecting communities.",
          ".hero .lead": "Pura Capoeira is a school and community dedicated to preserving, teaching, and sharing capoeira as art, fight, music, Afro-Brazilian culture, discipline, and personal development.",
          "[data-testid='hero-cta-sedes']": "Find a location",
          "[data-testid='hero-cta-grupo']": "Meet the group",
          "[data-testid='hero-cta-galeria']": "View gallery",
          ".section[data-testid='home-intro'] .eyebrow": "What is Pura Capoeira?",
          ".section[data-testid='home-intro'] h2.display": { html: "One<br/>community,<br/><em style=\"color:var(--green-deep);\">many roots.</em>" },
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .lead": "Pura Capoeira is a community of capoeiristas connected by practice, music, roda, Afro-Brazilian culture, and respect for capoeira history.",
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(1)": "Each location has its own local context, but shares one foundation: train with discipline, play with intelligence, sing with fundamentals, and strengthen community.",
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(2)": "Capoeira is fight, art, music, history, strategy, and personal development. It is not fitness, not dance, not just sport. It is living culture.",
          "[data-testid='intro-cta-grupo']": "Read the full philosophy",
          ".section[data-testid='home-sedes-section'] .eyebrow": "Active hubs",
          ".section[data-testid='home-sedes-section'] h2.display": { html: "Pura Capoeira<br/>Locations" },
          ".section[data-testid='home-sedes-section'] .section-head > p": "Professors, hubs, and community in six cities around the world. Find the location closest to you.",
          "#home-sedes .muted": "Loading locations...",
          ".cta-band h2.display": { html: "Ready<br/>to enter the roda?" },
          ".cta-band p": "Classes for adults, kids, teens, and youth. Music, jogo, and culture.",
          "[data-testid='cta-band-contacto']": "Contact us",
          "[data-testid='cta-band-sedes']": "View locations"
        },
        pt: {
          ".hero__meta .eyebrow:nth-of-type(1)": "Escola internacional · comunidade estabelecida",
          ".hero__meta .eyebrow--muted": "Mexico · Brasil · Angola · USA",
          ".hero .sub": "Capoeira, cultura, musica e movimento conectando comunidades.",
          ".hero .lead": "Pura Capoeira e uma escola e comunidade dedicada a preservar, ensinar e compartilhar a capoeira como arte, luta, musica, cultura afro-brasileira, disciplina e desenvolvimento pessoal.",
          "[data-testid='hero-cta-sedes']": "Encontre uma sede",
          "[data-testid='hero-cta-grupo']": "Conheca o grupo",
          "[data-testid='hero-cta-galeria']": "Ver galeria",
          ".section[data-testid='home-intro'] .eyebrow": "O que e Pura Capoeira?",
          ".section[data-testid='home-intro'] h2.display": { html: "Uma<br/>comunidade,<br/><em style=\"color:var(--green-deep);\">muitas raizes.</em>" },
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .lead": "Pura Capoeira e uma comunidade de capoeiristas conectados pela pratica, musica, roda, cultura afro-brasileira e respeito pela historia da capoeira.",
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(1)": "Cada sede tem seu contexto local, mas compartilha a mesma base: treinar com disciplina, jogar com inteligencia, cantar com fundamento e fortalecer a comunidade.",
          ".section[data-testid='home-intro'] .editorial > div:nth-of-type(2) .muted:nth-of-type(2)": "Capoeira e luta, arte, musica, historia, estrategia e desenvolvimento pessoal. Nao e fitness, nao e danca, nao e apenas esporte. E cultura viva.",
          "[data-testid='intro-cta-grupo']": "Ler a filosofia completa",
          ".section[data-testid='home-sedes-section'] .eyebrow": "Nucleos ativos",
          ".section[data-testid='home-sedes-section'] h2.display": { html: "Sedes da<br/>Pura Capoeira" },
          ".section[data-testid='home-sedes-section'] .section-head > p": "Professores, nucleos e comunidade em seis cidades ao redor do mundo. Encontre a sede mais proxima de voce.",
          "#home-sedes .muted": "Carregando sedes...",
          ".cta-band h2.display": { html: "Pronto<br/>para entrar na roda?" },
          ".cta-band p": "Aulas para adultos, kids, adolescentes e jovens. Musica, jogo e cultura.",
          "[data-testid='cta-band-contacto']": "Fale conosco",
          "[data-testid='cta-band-sedes']": "Ver sedes"
        }
      },
      "grupo.html": {
        en: {
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) .eyebrow": "Our history",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) h2.display": "Capoeira as a formation path.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.lead": "Pura Capoeira was born as a community dedicated to living capoeira fully: as fight, art, music, history, culture, and a path of personal formation.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(1)": "The school brings together professors, instructors, students, and training hubs in different cities, maintaining a common foundation of respect, discipline, musicality, and fundamentals.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) blockquote.pull-quote": "\"Capoeira is transmitted in the roda, in training, in singing, in the berimbau rhythm, and in the coexistence between generations.\"",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(2)": "That is why Pura Capoeira is not limited to teaching movements: it seeks to develop capoeiristas with body, musical, cultural, and historical awareness.",
          "main > section:nth-of-type(3) .section-head .eyebrow": "Values",
          "main > section:nth-of-type(3) .section-head h2.display": { html: "The pillars<br/>of Pura Capoeira" },
          "main > section:nth-of-type(3) .section-head > p": "Eight principles that guide our practice inside and outside the roda.",
          "#valores-grid .value-cell:nth-of-type(1) h3": "Discipline",
          "#valores-grid .value-cell:nth-of-type(1) p": "Consistency, presence, and commitment to practice.",
          "#valores-grid .value-cell:nth-of-type(2) h3": "Respect",
          "#valores-grid .value-cell:nth-of-type(2) p": "Toward the mestre, toward partners, toward history.",
          "#valores-grid .value-cell:nth-of-type(3) h3": "Musicality",
          "#valores-grid .value-cell:nth-of-type(3) p": "Berimbau, singing, and rhythm: the heart of the roda.",
          "#valores-grid .value-cell:nth-of-type(4) h3": "Afro-Brazilian culture",
          "#valores-grid .value-cell:nth-of-type(4) p": "Living memory, roots, and tradition.",
          "#valores-grid .value-cell:nth-of-type(5) h3": "Community",
          "#valores-grid .value-cell:nth-of-type(5) p": "Capoeira is not lived alone: it is lived with others.",
          "#valores-grid .value-cell:nth-of-type(6) h3": "Movement",
          "#valores-grid .value-cell:nth-of-type(6) p": "Conscious, fluid, and strong body.",
          "#valores-grid .value-cell:nth-of-type(7) h3": "Strategy",
          "#valores-grid .value-cell:nth-of-type(7) p": "The jogo is dialogue, intelligence, and malicia.",
          "#valores-grid .value-cell:nth-of-type(8) h3": "Foundation",
          "#valores-grid .value-cell:nth-of-type(8) p": "What sustains everything: tradition, ethics, and meaning.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(1) .eyebrow": "Our vision",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(1) h2.display": "Develop capoeiristas, not only practitioners.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.lead": "Develop capoeiristas capable of jogar, singing, playing instruments, understanding history, and representing capoeira with respect inside and outside the roda.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.muted": "We work with students of all ages - kids, teens, youth, and adults - to build a practice that lasts a lifetime."
        },
        pt: {
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) .eyebrow": "Nossa historia",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) h2.display": "Capoeira como caminho de formacao.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.lead": "Pura Capoeira nasce como uma comunidade dedicada a viver a capoeira de forma completa: como luta, arte, musica, historia, cultura e caminho de formacao pessoal.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(1)": "A escola reune professores, instrutores, alunos e nucleos de treino em diferentes cidades, mantendo uma base comum de respeito, disciplina, musicalidade e fundamento.",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) blockquote.pull-quote": "\"A capoeira se transmite na roda, no treino, no canto, no toque do berimbau e na convivencia entre geracoes.\"",
          "main > section:nth-of-type(2) .editorial > div:nth-of-type(2) p.muted:nth-of-type(2)": "Por isso, Pura Capoeira nao se limita a ensinar movimentos: busca formar capoeiristas com consciencia corporal, musical, cultural e historica.",
          "main > section:nth-of-type(3) .section-head .eyebrow": "Valores",
          "main > section:nth-of-type(3) .section-head h2.display": { html: "Os pilares<br/>da Pura Capoeira" },
          "main > section:nth-of-type(3) .section-head > p": "Oito principios que guiam nossa pratica dentro e fora da roda.",
          "#valores-grid .value-cell:nth-of-type(1) h3": "Disciplina",
          "#valores-grid .value-cell:nth-of-type(1) p": "Constancia, presenca e compromisso com a pratica.",
          "#valores-grid .value-cell:nth-of-type(2) h3": "Respeito",
          "#valores-grid .value-cell:nth-of-type(2) p": "Com o mestre, com o parceiro, com a historia.",
          "#valores-grid .value-cell:nth-of-type(3) h3": "Musicalidade",
          "#valores-grid .value-cell:nth-of-type(3) p": "Berimbau, canto e ritmo: o coracao da roda.",
          "#valores-grid .value-cell:nth-of-type(4) h3": "Cultura afro-brasileira",
          "#valores-grid .value-cell:nth-of-type(4) p": "Memoria viva, raizes e tradicao.",
          "#valores-grid .value-cell:nth-of-type(5) h3": "Comunidade",
          "#valores-grid .value-cell:nth-of-type(5) p": "Capoeira nao se vive sozinho: se vive com os outros.",
          "#valores-grid .value-cell:nth-of-type(6) h3": "Movimento",
          "#valores-grid .value-cell:nth-of-type(6) p": "Corpo consciente, fluido e forte.",
          "#valores-grid .value-cell:nth-of-type(7) h3": "Estrategia",
          "#valores-grid .value-cell:nth-of-type(7) p": "O jogo e dialogo, inteligencia e malicia.",
          "#valores-grid .value-cell:nth-of-type(8) h3": "Fundamento",
          "#valores-grid .value-cell:nth-of-type(8) p": "O que sustenta tudo: tradicao, etica e sentido.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(1) .eyebrow": "Nossa visao",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(1) h2.display": "Formar capoeiristas, nao apenas praticantes.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.lead": "Formar capoeiristas capazes de jogar, cantar, tocar, compreender a historia e representar a capoeira com respeito dentro e fora da roda.",
          "main > section:nth-of-type(4) .editorial > div:nth-of-type(2) p.muted": "Trabalhamos com alunos de todas as idades - kids, adolescentes, jovens e adultos - para construir uma pratica que dura toda a vida."
        }
      },
      "galeria.html": {
        en: {
          "#gallery-grid .muted": "Loading gallery...",
          "main > section.section .container > p.muted": { firstText: "Want to see more? Visit Cuernavaca's public album:" },
          "#icloud-link": "Pura Capoeira Cuernavaca iCloud Album →"
        },
        pt: {
          "#gallery-grid .muted": "Carregando galeria...",
          "main > section.section .container > p.muted": { firstText: "Quer ver mais? Visite o album publico de Cuernavaca:" },
          "#icloud-link": "Album iCloud Pura Capoeira Cuernavaca →"
        }
      },
      "eventos.html": {
        en: { "#event-list > p.muted": "Loading events..." },
        pt: { "#event-list > p.muted": "Carregando eventos..." }
      },
      "profesores.html": {
        en: { "#prof-grid .muted": "Loading professors..." },
        pt: { "#prof-grid .muted": "Carregando professores..." }
      },
      "sedes.html": {
        en: { "#sedes-grid .muted": "Loading locations..." },
        pt: { "#sedes-grid .muted": "Carregando sedes..." }
      },
      "contacto.html": {
        en: { "#contact-cards .muted": "Loading contacts..." },
        pt: { "#contact-cards .muted": "Carregando contatos..." }
      },
      "cuernavaca.html": {
        en: {
          ".page-hero .eyebrow": "Location · Cuernavaca · Mexico",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Cuernavaca · Mexico",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "toluca.html": {
        en: {
          ".page-hero .eyebrow": "Location · Toluca · Mexico",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Toluca · Mexico",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "guanajuato.html": {
        en: {
          ".page-hero .eyebrow": "Location · Guanajuato · Mexico",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Guanajuato · Mexico",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "ceara.html": {
        en: {
          ".page-hero .eyebrow": "Location · Ceara · Brazil",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Ceara · Brasil",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "angola.html": {
        en: {
          ".page-hero .eyebrow": "Location · Angola",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Angola",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "austin.html": {
        en: {
          ".page-hero .eyebrow": "Location · Austin · Texas · USA",
          "#sede-sub": "Loading information..."
        },
        pt: {
          ".page-hero .eyebrow": "Sede · Austin · Texas · USA",
          "#sede-sub": "Carregando informacoes..."
        }
      },
      "profesor-mestre-madona.html": {
        en: {
          ".info-block:nth-of-type(1)": { html: "<h3>Biography</h3><p>Mestre Madona, Mardonio Sales Linhares, is from <strong>Fortaleza, Ceará, Brazil</strong>. His path in capoeira was built through training, teaching, movement, music, cultural transmission, and respect for the fundamentos of the art. His formation was shaped by the mestres who marked his development, including Mestre Soldado, Mestre Burguês, and Mestre Squisito. Through these references, he developed a capoeira practice rooted in tradition, discipline, musicality, the roda, Afro-Brazilian culture, and the responsibility of passing knowledge to new generations.</p><p>In 2005, Mestre Madona began one of the most important chapters of his life when he took capoeira to Mexico. What started as a new path outside Brazil became a twenty-year trajectory of teaching, organization, cultural resistance, and community building. His work began in León, Guanajuato, where he taught in universities, academies, and local spaces, helping introduce and strengthen capoeira in a Mexican context.</p><p>During his first years in Mexico, he taught at institutions such as Universidad Iberoamericana de León, Instituto Tecnológico de Monterrey Campus León, Escuela de Diseño de la Universidad de Guanajuato, and local academies. In that same period, he launched the first capoeira magazine in Mexico between 2007 and 2008, a project carried out with the support of a dedicated team of students. This magazine became an important cultural and professional achievement, helping document and promote capoeira in the country.</p><p>From 2008 to 2019, Mestre Madona established himself in Toluca, Estado de México, where his work entered a period of expansion and consolidation. He continued teaching in institutions such as Tec Milenio, Tec de Monterrey, and the Universidad Autónoma del Estado de México. During this stage, he opened his own academy, founded his own group, formed numerous Mexican capoeiristas, and coordinated work in different cities across the country.</p><p>In 2010, he received his Mestre formatura in Rio de Janeiro, Brazil, during the major event of Grupo Muzenza, led by Mestre Burguês. This moment confirmed his place within the capoeira lineage that had shaped his development and strengthened his role as a teacher and cultural representative beyond Brazil.</p><p>In 2014, he founded <strong>Centro Esportivo Cultural Pura Capoeira</strong>, marking the beginning of an independent phase of leadership, consolidation, and identity. Through Pura Capoeira, Mestre Madona built a school focused on complete capoeira formation: movement, music, instruments, singing, history, discipline, respect, community, and Afro-Brazilian cultural resistance. Today, his work continues through students, professors, instructors, contramestres, and group leaders who carry forward the training and values developed through his teaching.</p>" },
          ".info-block:nth-of-type(2)": { html: "<h3>Trajectory</h3><p>Mestre Madona's trajectory in Mexico began in 2005, when he left Brazil to bring capoeira and its philosophy of life into a new cultural territory. In 2006, the first major events began to take shape, including the 1st Copa Mexicana de Capoeira with the presence of Mestre Burguês and the first capoeira circuit in León, Mexico City, and Nezahualcóyotl.</p><p>In 2007, he helped develop the 1st Encuentro Intercultural de Capoeira, with Brazilian professors, a professor from France, and the presence of Mestre Burguês. Between 2007 and 2008, he launched the first capoeira magazine in Mexico, a pioneering editorial project created with the support of his students. In 2008, the expansion continued with the 2nd Capoeira Circuit in León, Mexico City, and Guadalajara, in collaboration with other professors.</p><p>In 2009, the 2nd Copa Mexicana de Capoeira was held with local professors and Mestre Aranha. This period also marked the beginning of an important friendship and parceria with Contramestre Cipó. In 2010, Mestre Madona received his Mestre formatura in Rio de Janeiro during Grupo Muzenza's major event under the leadership of Mestre Burguês. That same year, the second intercultural encounter took place in Toluca.</p><p>In 2011, the 3rd Copa Mexicana de Capoeira was held in Toluca with the presence of Mestre Nelson from Grupo Muzenza. That year, Mestre Madona also traveled through Europe giving courses through Grupo Muzenza and Mestre Burguês, strengthening his international profile. His international work expanded through courses, workshops, and event participation in Portugal, Spain, France, Israel, England, Panama, Colombia, and the United States, in addition to travel throughout Mexico from north to south.</p><p>In 2012, an event in Toluca featured Mestre Girafa from Alagoas, Brazil, whose personal path reinforced values of inclusion, respect, and diversity within the group. In 2013, an internal event in Toluca, organized under Grupo Muzenza standards, gathered students and professors from the region and celebrated the growth of the team under Mestre Madona's coordination.</p><p>In 2014, he founded Centro Esportivo Cultural Pura Capoeira, beginning his independent phase and consolidating his own direction. The foundation of the group included the participation of respected mestres from Brazil and helped define a new institutional identity for his work. In 2015, the Encuentro de Camaradas marked an important stage of friendship and alliances after the group's foundation. That year, Mestre Madona also participated in international events in the United States by invitation of Mestre Chuvisco and had the honor of spending time with Mestre João Grande.</p><p>In 2016, the 5th Copa Mexicana de Capoeira brought together Mestre Ganso, Mestre Pelourinho, Contramestre Cipó, and close friends of the group. In 2017, the group began a fusion with Mestre Auricélio from Brazil and adopted the name <strong>Pura Capoeira em Movimento</strong>, a name that lasted seven years before the group returned to its original name in 2024. In 2018, the Copa Mexicana de Capoeira had already become a traditional event held every two years, with the presence of Mestre Auricélio from Brazil and Mestre Demétrius from the United States.</p><p>In 2019, Mestre Madona moved to the United States, leaving a team in Mexico responsible for coordinating the work while he continued supervising and forming local professors. That same year, the team organized \"Capoeira não pode parar\" in Toluca, with Professora Ligis at the front, marking the first major event after his departure and emphasizing the value of Mexican talent within the group.</p><p>In 2020, during the COVID-19 pandemic, the group sustained its work through virtual training. Despite uncertainty and distance, Pura Capoeira México remained active through creative online classes, with Professora Ligis and Professora Lua standing out in the virtual movement of the group. In 2021, the academy in Toluca had closed, but the group continued training remotely and was able to connect members from different states and countries.</p><p>In 2022, Mestre Madona participated in the first post-pandemic in-person event in Mexico, held in Guanajuato with the presence of Mestre Touro and organized by Professors Pepe Mortales and Rochedo. In 2023, the group marked an important moment with the formaturas of Professora Ligis and Professor Conde as contramestres, showing the continuity of the work and the strength of the team in Toluca.</p><p>By 2024, Cuernavaca, Morelos, had become a stable center for annual encounters under the organization of Professor Malandro, with Mestre Madona participating as supervisor and invited mestre. In 2025, the work projects new books, formaturas, classes, and intense rodas, with Professor Malandro leading local organization and Mestre Madona continuing as supervisor and reference for the group.</p><p>Mestre Madona's trajectory is not simply a record of events. It is the construction of a capoeira network between Brazil, Mexico, the United States, and the wider world. His work has formed capoeiristas, opened spaces for rodas and encounters, created editorial and cultural projects, survived geographic distance and the pandemic, and continued through the students and teachers who carry Pura Capoeira forward.</p>" }
        },
        pt: {
          ".info-block:nth-of-type(1)": { html: "<h3>Biografia</h3><p>Mestre Madona, Mardonio Sales Linhares, é natural de <strong>Fortaleza, Ceará, Brasil</strong>. Sua caminhada na capoeira foi construída por meio do treino, do ensino, do movimento, da música, da transmissão cultural e do respeito aos fundamentos da arte. Sua formação foi marcada por mestres que influenciaram diretamente seu desenvolvimento, entre eles Mestre Soldado, Mestre Burguês e Mestre Squisito. A partir dessas referências, consolidou uma prática de capoeira ligada à tradição, à disciplina, à musicalidade, à roda, à cultura afro-brasileira e à responsabilidade de transmitir conhecimento às novas gerações.</p><p>Em 2005, Mestre Madona iniciou uma das etapas mais importantes de sua vida ao levar a capoeira para o México. O que começou como um novo caminho fora do Brasil tornou-se uma trajetória de vinte anos dedicada ao ensino, à organização, à resistência cultural e à construção de comunidade. Seu trabalho começou em León, Guanajuato, onde ministrou aulas em universidades, academias e espaços locais, contribuindo para introduzir e fortalecer a capoeira no contexto mexicano.</p><p>Durante seus primeiros anos no México, ministrou aulas em instituições como a Universidad Iberoamericana de León, o Instituto Tecnológico de Monterrey Campus León, a Escuela de Diseño de la Universidad de Guanajuato e academias locais. Nesse mesmo período, lançou a primeira revista de capoeira no México, entre 2007 e 2008, um projeto realizado com o apoio de uma equipe comprometida de alunos. Essa revista representou uma conquista cultural e profissional importante, ao documentar e promover a capoeira no país.</p><p>De 2008 a 2019, Mestre Madona se estabeleceu em Toluca, Estado do México, onde seu trabalho entrou em uma fase de expansão e consolidação. Continuou ministrando aulas em instituições como Tec Milenio, Tec de Monterrey e Universidad Autónoma del Estado de México. Durante essa etapa, abriu sua própria academia, fundou seu próprio grupo, formou numerosos capoeiristas mexicanos e coordenou trabalhos em diferentes cidades do país.</p><p>Em 2010, recebeu sua formatura de Mestre no Rio de Janeiro, Brasil, durante o evento maior do Grupo Muzenza, liderado por Mestre Burguês. Esse momento confirmou seu lugar dentro da linhagem de capoeira que havia marcado seu desenvolvimento e fortaleceu seu papel como professor e representante cultural além das fronteiras do Brasil.</p><p>Em 2014, fundou o <strong>Centro Esportivo Cultural Pura Capoeira</strong>, marcando o início de uma fase independente de liderança, consolidação e identidade própria. Por meio da Pura Capoeira, Mestre Madona construiu uma escola voltada à formação integral do capoeirista: movimento, música, instrumentos, canto, história, disciplina, respeito, comunidade e resistência cultural afro-brasileira. Hoje, seu trabalho continua por meio de alunos, professores, instrutores, contramestres e lideranças que sustentam a formação e os valores desenvolvidos por seu ensino.</p>" },
          ".info-block:nth-of-type(2)": { html: "<h3>Trajetória</h3><p>A trajetória de Mestre Madona no México começou em 2005, quando saiu do Brasil para levar a capoeira e sua filosofia de vida a um novo território cultural. Em 2006, os primeiros grandes eventos começaram a tomar forma, incluindo a 1ª Copa Mexicana de Capoeira com a presença de Mestre Burguês e o primeiro circuito de capoeira em León, Cidade do México e Nezahualcóyotl.</p><p>Em 2007, participou do desenvolvimento do 1º Encontro Intercultural de Capoeira, com professores brasileiros, um professor da França e a presença de Mestre Burguês. Entre 2007 e 2008, lançou a primeira revista de capoeira no México, um projeto editorial pioneiro criado com o apoio de seus alunos. Em 2008, a expansão continuou com o 2º Circuito de Capoeira em León, Cidade do México e Guadalajara, em colaboração com outros professores.</p><p>Em 2009, foi realizada a 2ª Copa Mexicana de Capoeira com professores locais e Mestre Aranha. Esse período também marcou o início de uma amizade e parceria importante com Contramestre Cipó. Em 2010, Mestre Madona recebeu sua formatura de Mestre no Rio de Janeiro durante o evento maior do Grupo Muzenza, sob a liderança de Mestre Burguês. Nesse mesmo ano, aconteceu o segundo encontro intercultural em Toluca.</p><p>Em 2011, foi realizada a 3ª Copa Mexicana de Capoeira em Toluca, com a presença de Mestre Nelson do Grupo Muzenza. No mesmo ano, Mestre Madona realizou uma viagem pela Europa ministrando cursos por meio do Grupo Muzenza e de Mestre Burguês, fortalecendo seu perfil internacional. Seu trabalho internacional se ampliou por meio de cursos, oficinas e participação em eventos em Portugal, Espanha, França, Israel, Inglaterra, Panamá, Colômbia e Estados Unidos, além de percorrer o México de norte a sul.</p><p>Em 2012, um evento em Toluca contou com a presença de Mestre Girafa, de Alagoas, Brasil, cuja trajetória pessoal reforçou valores de inclusão, respeito e diversidade dentro do grupo. Em 2013, foi realizado um evento interno em Toluca, organizado segundo as normas do Grupo Muzenza, reunindo alunos e professores da região e celebrando o crescimento da equipe sob a coordenação de Mestre Madona.</p><p>Em 2014, fundou o Centro Esportivo Cultural Pura Capoeira, iniciando sua fase independente e consolidando uma direção própria. A fundação do grupo contou com a participação de mestres reconhecidos do Brasil e ajudou a definir uma nova identidade institucional para seu trabalho. Em 2015, o Encontro de Camaradas marcou uma etapa importante de amizades e alianças depois da fundação do grupo. Nesse mesmo ano, Mestre Madona participou de eventos internacionais nos Estados Unidos a convite de Mestre Chuvisco e teve a honra de conviver com Mestre João Grande.</p><p>Em 2016, a 5ª Copa Mexicana de Capoeira reuniu Mestre Ganso, Mestre Pelourinho, Contramestre Cipó e amigos próximos do grupo. Em 2017, o grupo iniciou uma fusão com Mestre Auricélio, do Brasil, e adotou o nome <strong>Pura Capoeira em Movimento</strong>, nome que permaneceu por sete anos antes do retorno ao nome original em 2024. Em 2018, a Copa Mexicana de Capoeira já estava consolidada como um evento tradicional realizado a cada dois anos, contando com a presença de Mestre Auricélio do Brasil e Mestre Demétrius dos Estados Unidos.</p><p>Em 2019, Mestre Madona mudou-se para os Estados Unidos, deixando uma equipe no México responsável por coordenar o trabalho enquanto ele continuava supervisionando e formando professores locais. Nesse mesmo ano, a equipe organizou \"Capoeira não pode parar\" em Toluca, com Professora Ligis à frente, marcando o primeiro grande evento após sua partida e destacando o valor do talento mexicano dentro do grupo.</p><p>Em 2020, durante a pandemia de COVID-19, o grupo sustentou seu trabalho por meio de treinos virtuais. Apesar da incerteza e da distância, Pura Capoeira México permaneceu ativa por meio de aulas online criativas, com Professora Ligis e Professora Lua se destacando no movimento virtual do grupo. Em 2021, a academia de Toluca já havia fechado suas portas, mas o grupo continuou treinando de forma remota e conseguiu conectar integrantes de diferentes estados e países.</p><p>Em 2022, Mestre Madona participou do primeiro evento presencial no México após a pandemia, realizado em Guanajuato com a presença de Mestre Touro e sob a organização dos Professores Pepe Mortales e Rochedo. Em 2023, o grupo viveu um momento importante com as formaturas de Professora Ligis e Professor Conde como contramestres, mostrando a continuidade do trabalho e a força da equipe em Toluca.</p><p>Em 2024, Cuernavaca, Morelos, consolidou-se como um polo estável para os encontros anuais sob a organização do Professor Malandro, com Mestre Madona participando como supervisor e convidado. Em 2025, o trabalho projeta novos livros, formaturas, aulas e rodas intensas, com Professor Malandro à frente da organização local e Mestre Madona continuando como supervisor e referência do grupo.</p><p>A trajetória de Mestre Madona não é apenas uma sequência de eventos. É a construção de uma rede de capoeira entre Brasil, México, Estados Unidos e o mundo. Seu trabalho formou capoeiristas, abriu espaços para rodas e encontros, criou projetos editoriais e culturais, resistiu à distância geográfica e à pandemia, e continua por meio dos alunos e professores que mantêm vivo o caminho da Pura Capoeira.</p>" }
        }
      }
    };

    const page = extras[file] && extras[file][currentLang];
    if (!page) return;

    Object.entries(page).forEach(([sel, value]) => {
      if (typeof value === "string") {
        setText(sel, value);
        return;
      }
      if (value && typeof value === "object" && Object.prototype.hasOwnProperty.call(value, "html")) {
        setHTML(sel, value.html);
        return;
      }
      if (value && typeof value === "object" && Object.prototype.hasOwnProperty.call(value, "firstText")) {
        setFirstTextNode(sel, value.firstText);
      }
    });
  }

  function translateStaticPage() {
    const file = currentFileName();

    restoreOriginalStaticText();

    const profileSocial = {
      en: {
        ".prof-social h3": "Social and contact",
        ".prof-social__item:nth-of-type(1) span:first-child": "Instagram",
        ".prof-social__item:nth-of-type(2) span:first-child": "YouTube",
        ".prof-social__item:nth-of-type(3) span:first-child": "Facebook",
        ".prof-social__item:nth-of-type(4) span:first-child": "Website",
        ".prof-social__item:nth-of-type(5) span:first-child": "WhatsApp",
        ".prof-social__pending": "Link coming soon"
      },
      pt: {
        ".prof-social h3": "Redes e contato",
        ".prof-social__item:nth-of-type(1) span:first-child": "Instagram",
        ".prof-social__item:nth-of-type(2) span:first-child": "YouTube",
        ".prof-social__item:nth-of-type(3) span:first-child": "Facebook",
        ".prof-social__item:nth-of-type(4) span:first-child": "Site",
        ".prof-social__item:nth-of-type(5) span:first-child": "WhatsApp",
        ".prof-social__pending": "Link em breve"
      }
    };

    const map = {
      "index.html": {
        en: {
          "main .hero .eyebrow": "Capoeira · Culture · Community",
          "main .hero .sub": "International capoeira school and movement",
          "main .hero .btn--primary": "Find a location",
          "main .hero .btn--outline": "Meet the professors"
        },
        pt: {
          "main .hero .eyebrow": "Capoeira · Cultura · Comunidade",
          "main .hero .sub": "Escola internacional de capoeira e movimento",
          "main .hero .btn--primary": "Encontrar uma sede",
          "main .hero .btn--outline": "Conhecer professores"
        }
      },
      "grupo.html": {
        en: {
          ".page-hero .eyebrow": "Identity · Philosophy · Community",
          ".page-hero h1": "The Group",
          ".page-hero p": "A capoeira school connected by culture, discipline, music, and community.",
          "[data-testid='grupo-cta-sedes']": "Find a location",
          "[data-testid='grupo-cta-profesores']": "Meet professors"
        },
        pt: {
          ".page-hero .eyebrow": "Identidade · Filosofia · Comunidade",
          ".page-hero h1": "O Grupo",
          ".page-hero p": "Uma escola de capoeira conectada por cultura, disciplina, musica e comunidade.",
          "[data-testid='grupo-cta-sedes']": "Encontrar uma sede",
          "[data-testid='grupo-cta-profesores']": "Conhecer professores"
        }
      },
      "profesores.html": {
        en: {
          ".page-hero .eyebrow": "Masters · Professors · Instructors",
          ".page-hero h1": "Professors and instructors",
          ".page-hero p": "Meet the leaders of Pura Capoeira locations and training cores."
        },
        pt: {
          ".page-hero .eyebrow": "Mestres · Professores · Instrutores",
          ".page-hero h1": "Professores e instrutores",
          ".page-hero p": "Conheca os responsaveis pelas sedes e nucleos da Pura Capoeira."
        }
      },
      "sedes.html": {
        en: {
          ".page-hero .eyebrow": "International hubs",
          ".page-hero h1": "Pura Capoeira locations",
          ".page-hero p": "Find a location, training core, or professor near you."
        },
        pt: {
          ".page-hero .eyebrow": "Nucleos internacionais",
          ".page-hero h1": "Sedes da Pura Capoeira",
          ".page-hero p": "Encontre uma sede, nucleo ou professor perto de voce."
        }
      },
      "galeria.html": {
        en: {
          ".page-hero .eyebrow": "Images · Videos · Living memory",
          ".page-hero h1": "Gallery",
          ".page-hero p": "Videos and moments from classes, rodas, training, events, batizados, and group gatherings.",
          ".filter-group:nth-of-type(1) .filter-group__label": "Category",
          ".filter-group:nth-of-type(2) .filter-group__label": "Location"
        },
        pt: {
          ".page-hero .eyebrow": "Imagens · Videos · Memoria viva",
          ".page-hero h1": "Galeria",
          ".page-hero p": "Videos e momentos de aulas, rodas, treinos, eventos, batizados e encontros do grupo.",
          ".filter-group:nth-of-type(1) .filter-group__label": "Categoria",
          ".filter-group:nth-of-type(2) .filter-group__label": "Sede"
        }
      },
      "eventos.html": {
        en: {
          ".page-hero .eyebrow": "Rodas · Batizados · Gatherings",
          ".page-hero h1": "Events",
          ".page-hero p": "Rodas, batizados, gatherings, workshops, and Pura Capoeira activities."
        },
        pt: {
          ".page-hero .eyebrow": "Rodas · Batizados · Encontros",
          ".page-hero h1": "Eventos",
          ".page-hero p": "Rodas, batizados, encontros, oficinas e atividades da Pura Capoeira."
        }
      },
      "contacto.html": {
        en: {
          ".page-hero .eyebrow": "Get in touch",
          ".page-hero h1": "Contact",
          ".page-hero p": "Find your nearest Pura Capoeira location or write to us for more information.",
          ".contact-grid > div:first-child .eyebrow": "By location",
          ".contact-grid > div:first-child h2": "Local contacts",
          ".contact-grid > div:last-child .eyebrow": "General form",
          ".contact-grid > div:last-child h2": "Write to us"
        },
        pt: {
          ".page-hero .eyebrow": "Fale conosco",
          ".page-hero h1": "Contato",
          ".page-hero p": "Encontre a sede da Pura Capoeira mais proxima ou escreva para mais informacoes.",
          ".contact-grid > div:first-child .eyebrow": "Por sede",
          ".contact-grid > div:first-child h2": "Contatos locais",
          ".contact-grid > div:last-child .eyebrow": "Formulario geral",
          ".contact-grid > div:last-child h2": "Escreva para nos"
        }
      },
      "profesor-malandro.html": {
        en: {
          ".page-hero .eyebrow": "Professor · Cuernavaca, Morelos, Mexico",
          ".page-hero p": "Alfredo Juliano Prince leads Pura Capoeira Cuernavaca with focus on discipline, movement, music, culture, strategy, and personal development.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Professor Malandro, Alfredo Juliano Prince, started his capoeira path in 2005 at the University of the Virgin Islands.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Later he continued his development in Cuernavaca, Morelos, where he has been part of the local capoeira community since 2009.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "He trained with Ginga no Guetto until 2016 and that same year began his path with Mestre Madona and Pura Capoeira.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "In September 2017 he completed instructor training in Cuernavaca during the 2nd National Circuit.",
          ".info-block:nth-of-type(2) p:nth-of-type(3)": "In 2022 he received his first Professor degree in Guanajuato, and in December 2024 his second Professor degree in Caucaia, Ceara, Brazil.",
          ".info-block:nth-of-type(2) p:nth-of-type(4)": "He has taught capoeira since 2015 and has shared this art in Mexico, the Virgin Islands, Fortaleza, and Caucaia.",
          ".info-block:nth-of-type(2) p:nth-of-type(5)": "Today he leads Pura Capoeira Cuernavaca focused on discipline, movement, music, culture, strategy, and personal growth.",
          ".prof-social h3": "Social and contact",
          ".prof-social__item:nth-of-type(2) .prof-social__pending": "Link coming soon"
        },
        pt: {
          ".page-hero .eyebrow": "Professor · Cuernavaca, Morelos, Mexico",
          ".page-hero p": "Alfredo Juliano Prince lidera a Pura Capoeira Cuernavaca com foco em disciplina, movimento, musica, cultura, estrategia e desenvolvimento pessoal.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Professor Malandro, Alfredo Juliano Prince, iniciou seu caminho na capoeira em 2005 na University of the Virgin Islands.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Depois continuou seu desenvolvimento em Cuernavaca, Morelos, onde faz parte da comunidade capoeirista local desde 2009.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Treinou com Ginga no Guetto ate 2016 e nesse mesmo ano iniciou seu caminho com Mestre Madona e Pura Capoeira.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Em setembro de 2017 concluiu sua formacao como instrutor em Cuernavaca durante o 2o Circuito Nacional.",
          ".info-block:nth-of-type(2) p:nth-of-type(3)": "Em 2022 recebeu seu primeiro grau de Professor em Guanajuato e, em dezembro de 2024, o segundo grau em Caucaia, Ceara, Brasil.",
          ".info-block:nth-of-type(2) p:nth-of-type(4)": "Ensina capoeira desde 2015 e compartilhou essa arte no Mexico, Ilhas Virgens, Fortaleza e Caucaia.",
          ".info-block:nth-of-type(2) p:nth-of-type(5)": "Hoje dirige a Pura Capoeira Cuernavaca com foco em disciplina, movimento, musica, cultura, estrategia e desenvolvimento pessoal.",
          ".prof-social h3": "Redes e contato",
          ".prof-social__item:nth-of-type(2) .prof-social__pending": "Link em breve"
        }
      },
      "profesor-mestre-madona.html": {
        en: {
          ".page-hero .eyebrow": "Founding Mestre of Pura Capoeira",
          ".page-hero p": "Founder of Pura Capoeira. A leader and reference in building community through capoeira.",
          ".prof-profile__caption p": "Founder of Pura Capoeira",
          "#madona-timeline-title": "Timeline",
          ".timeline-item:nth-of-type(1) h4": "Beginning of the Mexican Stage",
          ".timeline-item:nth-of-type(1) p": "Mestre Madona begins his mission to bring capoeira to Mexico and spread its life philosophy in a new territory.",
          ".timeline-item:nth-of-type(2) h4": "1st Mexican Cup and First Circuit",
          ".timeline-item:nth-of-type(2) p": "The 1st Mexican Capoeira Cup takes place with the presence of Mestre Burguês, along with the first circuit in León, Mexico City, and Nezahualcóyotl.",
          ".timeline-item:nth-of-type(3) h4": "1st Intercultural Encounter",
          ".timeline-item:nth-of-type(3) p": "The 1st Intercultural Capoeira Encounter takes place with Brazilian professors, a French professor, and the presence of Mestre Burguês.",
          ".timeline-item:nth-of-type(4) h4": "First Capoeira Magazine in Mexico",
          ".timeline-item:nth-of-type(4) p": "He drives the launch of the first capoeira magazine in Mexico with student support, marking a stage of cultural dissemination, collective organization, and professional achievement.",
          ".timeline-item:nth-of-type(5) h4": "2nd Capoeira Circuit",
          ".timeline-item:nth-of-type(5) p": "Expansion continues with the 2nd Capoeira Circuit in León, Mexico City, and Guadalajara, in collaboration with other professors.",
          ".timeline-item:nth-of-type(6) h4": "2nd Mexican Cup and Key Alliance",
          ".timeline-item:nth-of-type(6) p": "The 2nd Mexican Capoeira Cup is celebrated with local professors and Mestre Aranha; during this period a friendship and parceria with Contramestre Cipó is born.",
          ".timeline-item:nth-of-type(7) h4": "Formatura as Mestre",
          ".timeline-item:nth-of-type(7) p": "He receives his formatura as Mestre in Rio de Janeiro during the grand event of Grupo Muzenza led by Mestre Burguês; that same year the 2nd Intercultural Encounter is held in Toluca.",
          ".timeline-item:nth-of-type(8) h4": "3rd Cup and European Tour",
          ".timeline-item:nth-of-type(8) p": "The 3rd Mexican Cup is held in Toluca with the presence of Mestre Nelson; that same year Mestre Madona goes on a European tour teaching courses with Grupo Muzenza as reference.",
          ".timeline-item:nth-of-type(9) h4": "Team Consolidation in Toluca",
          ".timeline-item:nth-of-type(9) p": "He organizes an event with Mestre Girafa (2012) and later an internal event under the norms of Grupo Muzenza (2013), celebrating the growth and consolidation of the team.",
          ".timeline-item:nth-of-type(10) h4": "Founding of Pura Capoeira",
          ".timeline-item:nth-of-type(10) p": "He founds Centro Esportivo Cultural Pura Capoeira and begins an independent stage of consolidation, own direction, and defined institutional identity.",
          ".timeline-item:nth-of-type(11) h4": "International Expansion and 5th Cup",
          ".timeline-item:nth-of-type(11) p": "He participates in events in the United States by invitation of Mestre Chuvisco and spends time with Mestre João Grande (2015); in 2016 the 5th Mexican Cup is celebrated with Mestre Ganso, Mestre Pelourinho, and Contramestre Cipó.",
          ".timeline-item:nth-of-type(12) h4": "Fusion and em Movimento Stage",
          ".timeline-item:nth-of-type(12) p": "The group begins a fusion with Mestre Auricélio and adopts the name Pura Capoeira em Movimento; the Mexican Cup is consolidated as a traditional event every two years.",
          ".timeline-item:nth-of-type(13) h4": "Move to the United States",
          ".timeline-item:nth-of-type(13) p": "Mestre Madona moves to the United States and leaves a responsible team to coordinate the work in Mexico; in Toluca the Capoeira não pode parar event is held with Professora Ligis at the helm.",
          ".timeline-item:nth-of-type(14) h4": "Pandemic and In-Person Reactivation",
          ".timeline-item:nth-of-type(14) p": "The group sustains virtual training and events during the pandemic; in 2022 the first major in-person event returns to Mexico, held in Guanajuato with the presence of Mestre Touro.",
          ".timeline-item:nth-of-type(15) h4": "New Formaturas and Projection",
          ".timeline-item:nth-of-type(15) p": "New formaturas to contramestres are highlighted, the consolidation of Cuernavaca as an annual hub, and a 2025 projection with new book launches, formatura, classes, and intense rodas.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Mestre Fundador da Pura Capoeira",
          ".page-hero p": "Fundador da Pura Capoeira. Líder e referência na construção de comunidade por meio da capoeira.",
          ".prof-profile__caption p": "Fundador da Pura Capoeira",
          "#madona-timeline-title": "Linha do tempo",
          ".timeline-item:nth-of-type(1) h4": "Início da etapa mexicana",
          ".timeline-item:nth-of-type(1) p": "Mestre Madona inicia a missão de levar a capoeira ao México e difundir sua filosofia de vida em um novo território.",
          ".timeline-item:nth-of-type(2) h4": "1ª Copa Mexicana e primeiro circuito",
          ".timeline-item:nth-of-type(2) p": "Realiza-se a 1ª Copa Mexicana de Capoeira com a presença de Mestre Burguês, junto com o primeiro circuito em León, Cidade do México e Nezahualcóyotl.",
          ".timeline-item:nth-of-type(3) h4": "1º Encontro Intercultural",
          ".timeline-item:nth-of-type(3) p": "Realiza-se o 1º Encontro Intercultural de Capoeira com professores brasileiros, um professor da França e a presença de Mestre Burguês.",
          ".timeline-item:nth-of-type(4) h4": "Primeira revista de capoeira no México",
          ".timeline-item:nth-of-type(4) p": "Impulsiona o lançamento da primeira revista de capoeira no México com apoio de alunos, marcando uma etapa de difusão cultural, organização coletiva e realização profissional.",
          ".timeline-item:nth-of-type(5) h4": "2º Circuito de Capoeira",
          ".timeline-item:nth-of-type(5) p": "Continua a expansão com o 2º Circuito de Capoeira em León, Cidade do México e Guadalajara, em colaboração com outros professores.",
          ".timeline-item:nth-of-type(6) h4": "2ª Copa Mexicana e aliança chave",
          ".timeline-item:nth-of-type(6) p": "Celebra-se a 2ª Copa Mexicana de Capoeira com professores locais e Mestre Aranha; nesse período nasce a amizade e parceria com Contramestre Cipó.",
          ".timeline-item:nth-of-type(7) h4": "Formatura como Mestre",
          ".timeline-item:nth-of-type(7) p": "Recebe sua formatura de Mestre no Rio de Janeiro durante o evento maior do Grupo Muzenza liderado por Mestre Burguês; nesse mesmo ano realiza-se o 2º Encontro Intercultural em Toluca.",
          ".timeline-item:nth-of-type(8) h4": "3ª Copa e turnê pela Europa",
          ".timeline-item:nth-of-type(8) p": "Realiza-se a 3ª Copa Mexicana em Toluca com a presença de Mestre Nelson; nesse mesmo ano Mestre Madona faz uma turnê pela Europa ministrando cursos com referência do Grupo Muzenza.",
          ".timeline-item:nth-of-type(9) h4": "Consolidação da equipe em Toluca",
          ".timeline-item:nth-of-type(9) p": "Organiza um evento com Mestre Girafa (2012) e depois um evento interno segundo as normas do Grupo Muzenza (2013), celebrando o crescimento e a consolidação da equipe.",
          ".timeline-item:nth-of-type(10) h4": "Fundação da Pura Capoeira",
          ".timeline-item:nth-of-type(10) p": "Funda o Centro Esportivo Cultural Pura Capoeira e inicia uma etapa independente de consolidação, direção própria e identidade institucional definida.",
          ".timeline-item:nth-of-type(11) h4": "Expansão internacional e 5ª Copa",
          ".timeline-item:nth-of-type(11) p": "Participa de eventos nos Estados Unidos a convite de Mestre Chuvisco e convive com Mestre João Grande (2015); em 2016 celebra-se a 5ª Copa Mexicana com Mestre Ganso, Mestre Pelourinho e Contramestre Cipó.",
          ".timeline-item:nth-of-type(12) h4": "Fusão e etapa em Movimento",
          ".timeline-item:nth-of-type(12) p": "O grupo inicia uma fusão com Mestre Auricélio e adota o nome Pura Capoeira em Movimento; a Copa Mexicana se consolida como evento tradicional a cada dois anos.",
          ".timeline-item:nth-of-type(13) h4": "Mudança para os Estados Unidos",
          ".timeline-item:nth-of-type(13) p": "Mestre Madona muda-se para os Estados Unidos e deixa uma equipe responsável por coordenar o trabalho no México; em Toluca realiza-se o evento Capoeira não pode parar com Professora Ligis à frente.",
          ".timeline-item:nth-of-type(14) h4": "Pandemia e reativação presencial",
          ".timeline-item:nth-of-type(14) p": "O grupo sustenta treinos e eventos virtuais durante a pandemia; em 2022 retorna o primeiro grande evento presencial no México, realizado em Guanajuato com a presença de Mestre Touro.",
          ".timeline-item:nth-of-type(15) h4": "Novas formaturas e projeção",
          ".timeline-item:nth-of-type(15) p": "Destacam-se formaturas a contramestres, a consolidação de Cuernavaca como polo anual e uma projeção 2025 com novos lançamentos de livros, formatura, aulas e rodas intensas.",
          ...profileSocial.pt
        }
      },
      "profesor-mestre-romim.html": {
        en: {
          ".page-hero .eyebrow": "Mestre · Fortaleza, Ceara, Brazil",
          ".page-hero p": "Reference for Pura Capoeira in Fortaleza, Ceara, Brazil.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Mestre Romim stands out for his technical and human development work with capoeiristas in Fortaleza.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "His practice keeps Afro-Brazilian tradition alive while strengthening the group's identity in each class and roda.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "With years of capoeira experience, his trajectory includes training work, events, and mentoring students at different levels.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "In Ceara, he promotes consistent training focused on technique, rhythm, and cultural foundations.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Mestre · Fortaleza, Ceara, Brasil",
          ".page-hero p": "Referencia da Pura Capoeira em Fortaleza, Ceara, Brasil.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Mestre Romim se destaca por seu trabalho na formacao tecnica e humana de capoeiristas em Fortaleza.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Sua pratica mantem viva a tradicao afro-brasileira enquanto fortalece a identidade do grupo em cada aula e roda.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Com anos de experiencia em capoeira, sua trajetoria inclui trabalho formativo, eventos e acompanhamento de alunos em diferentes niveis.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "No Ceara, impulsiona uma linha de treino consistente com foco em tecnica, ritmo e fundamentos culturais.",
          ...profileSocial.pt
        }
      },
      "profesor-mestre-junior-paludo.html": {
        en: {
          ".page-hero .eyebrow": "Mestre · Caucaia, Ceara, Brazil",
          ".page-hero p": "Teacher focused on technique, musicality, and foundational training.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Mestre Junior Paludo develops his work in Caucaia, Ceara, where he mentors children, youth, and adults.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "His approach combines body discipline, musicality, and game reading in the roda.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "His trajectory is connected to continuous training spaces, event participation, and consolidation of local groups.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "He promotes a living, technical capoeira with a strong community sense inside Pura Capoeira.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Mestre · Caucaia, Ceara, Brasil",
          ".page-hero p": "Formador com foco em tecnica, musicalidade e trabalho de base.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Mestre Junior Paludo desenvolve seu trabalho em Caucaia, Ceara, onde acompanha a formacao de criancas, jovens e adultos.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Seu enfoque integra disciplina corporal, musicalidade e leitura de jogo na roda.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria esta vinculada a espacos de treino continuo, participacao em eventos e consolidacao de grupos locais.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Promove uma capoeira viva, tecnica e com forte sentido comunitario dentro da Pura Capoeira.",
          ...profileSocial.pt
        }
      },
      "profesor-contramestre-pepe-mortales.html": {
        en: {
          ".page-hero .eyebrow": "Contramestre · Guanajuato, Guanajuato, Mexico",
          ".page-hero p": "Teacher and lead for training processes in Guanajuato.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Contramestre Pepe Mortales develops his work in Guanajuato, mentoring students with technical focus and steady training.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "His practice integrates values of respect, discipline, and belonging within the capoeira community.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "His trajectory includes leading classes, organizing rodas, and participating in exchange activities with other locations.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "He consolidates a training line focused on game evolution and the musical reading of each student.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Contramestre · Guanajuato, Guanajuato, Mexico",
          ".page-hero p": "Formador e responsavel por processos de treinamento em Guanajuato.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Contramestre Pepe Mortales desenvolve seu trabalho em Guanajuato, acompanhando a formacao de praticantes com foco tecnico e treino constante.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Sua pratica integra valores de respeito, disciplina e pertencimento a comunidade capoeirista.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria inclui conducao de aulas, organizacao de rodas e participacao em atividades de intercambio com outras sedes.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Consolida uma linha de treino focada na evolucao do jogo e na leitura musical de cada aluno.",
          ...profileSocial.pt
        }
      },
      "profesora-laura.html": {
        en: {
          ".page-hero .eyebrow": "Professor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "A training reference in Toluca, with technical and pedagogical focus.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Professor Laura is part of the Toluca team, where she develops teaching processes for different levels.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Her style focuses on consistency, technique, and close support for the community.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Her trajectory combines practice, teaching, and participation in integration activities with other locations.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "In Toluca, she promotes classes focused on progressive growth and stronger musicality.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Professora · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Referencia de formacao em Toluca, com foco tecnico e pedagogico.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Professora Laura faz parte da equipe de Toluca, onde desenvolve processos de ensino para diferentes niveis.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Seu estilo de trabalho foca em constancia, tecnica e acompanhamento proximo da comunidade.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria combina pratica, docencia e participacao em atividades de integracao com outras sedes.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Em Toluca, impulsiona aulas orientadas ao crescimento progressivo e ao fortalecimento da musicalidade.",
          ...profileSocial.pt
        }
      },
      "instructora-palito.html": {
        en: {
          ".page-hero .eyebrow": "Instructor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Part of the Toluca team focused on technical foundations and continuous training.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instructor Palito is part of the Toluca team and mentors students in their technical and body process within capoeira.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Her work prioritizes discipline, listening, and community building during each training session.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Her trajectory is strengthened through continuous practice, roda participation, and active mentoring of new practitioners.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "In Toluca, she promotes clear training in foundations, rhythm, and movement applied to the game.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Instrutora · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Parte da equipe de Toluca com foco em base tecnica e formacao continua.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instrutora Palito integra a equipe de Toluca e acompanha alunos em seu processo tecnico e corporal dentro da capoeira.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Seu trabalho prioriza disciplina, escuta e construcao de comunidade durante cada treino.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria se fortalece com pratica continua, participacao em rodas e acompanhamento ativo de novos praticantes.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Em Toluca, impulsiona uma formacao clara em fundamentos, ritmo e movimento aplicado ao jogo.",
          ...profileSocial.pt
        }
      },
      "instructor-dudu.html": {
        en: {
          ".page-hero .eyebrow": "Instructor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Toluca instructor focused on rhythm, endurance, and applied technique.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instructor Dudu actively participates in student development in Toluca, with special focus on building a solid technical base.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "His work combines physical training, rhythm, and attention to each practitioner's individual progress.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "His trajectory includes mentoring local groups, collaborating in events, and participating in exchange rodas.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "He supports a consistent training line to improve endurance, coordination, and game reading.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Instrutor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Instrutor de Toluca com foco em ritmo, resistencia e tecnica aplicada.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instrutor Dudu participa ativamente da formacao de alunos em Toluca, com foco especial na construcao de uma base tecnica solida.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Seu trabalho combina treino fisico, ritmo e atencao a progressao individual de cada praticante.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria inclui acompanhamento de grupos locais, colaboracao em eventos e participacao em rodas de intercambio.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Apoia uma linha de trabalho constante para melhorar resistencia, coordenacao e leitura do jogo.",
          ...profileSocial.pt
        }
      },
      "instructor-chino.html": {
        en: {
          ".page-hero .eyebrow": "Instructor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Instructor in Toluca focused on applied game and training consistency.",
          ".info-block:nth-of-type(1) h3": "Biography",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instructor Chino is part of the Toluca team, supporting students' technical development in different stages.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "His teaching style promotes discipline, musical listening, and progressive movement control.",
          ".info-block:nth-of-type(2) h3": "Trajectory",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "His trajectory includes foundational work, continuous practice, and participation in collective training and roda spaces.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "He contributes to strengthening the location through practice processes focused on technique, endurance, and conscious game.",
          ...profileSocial.en
        },
        pt: {
          ".page-hero .eyebrow": "Instrutor · Toluca, Edo. Mexico, Mexico",
          ".page-hero p": "Instrutor em Toluca, centrado em jogo aplicado e constancia de treino.",
          ".info-block:nth-of-type(1) h3": "Biografia",
          ".info-block:nth-of-type(1) p:nth-of-type(1)": "Instrutor Chino faz parte da equipe de Toluca, acompanhando o desenvolvimento tecnico de alunos em diferentes etapas.",
          ".info-block:nth-of-type(1) p:nth-of-type(2)": "Seu estilo de ensino promove disciplina, escuta musical e dominio progressivo do movimento.",
          ".info-block:nth-of-type(2) h3": "Trajetoria",
          ".info-block:nth-of-type(2) p:nth-of-type(1)": "Sua trajetoria considera trabalho de base, pratica continua e participacao em espacos coletivos de treino e roda.",
          ".info-block:nth-of-type(2) p:nth-of-type(2)": "Contribui para o fortalecimento da sede com processos de pratica orientados a tecnica, resistencia e jogo consciente.",
          ...profileSocial.pt
        }
      }
    };

    if (currentLang === "es") {
      document.documentElement.setAttribute("lang", "es");
      return;
    }

    const pageMap = map[file] && map[file][currentLang];
    if (pageMap) {
      Object.keys(pageMap).forEach(sel => {
        setText(sel, pageMap[sel]);
      });
    }

    if (file === "contacto.html") {
      setFirstTextNode("#contact-form label:nth-of-type(1)", t("common", "name"));
      setFirstTextNode("#contact-form label:nth-of-type(2)", t("common", "city"));
      setFirstTextNode("#contact-form label:nth-of-type(3)", t("common", "phone"));
      setFirstTextNode("#contact-form label:nth-of-type(4)", t("common", "message"));
      setText("#contact-form button[type='submit']", t("common", "sendMessage"));
      setText("#contact-form .muted", t("common", "openMailClient"));
    }

    if (file === "profesor-malandro.html") {
      const labels = currentLang === "en"
        ? { instagram: "Instagram", youtube: "YouTube", facebook: "Facebook", website: "Website", whatsapp: "WhatsApp" }
        : { instagram: "Instagram", youtube: "YouTube", facebook: "Facebook", website: "Site", whatsapp: "WhatsApp" };
      setIconLabel(".prof-social__item:nth-of-type(1) .prof-social__label", labels.instagram);
      setIconLabel(".prof-social__item:nth-of-type(2) .prof-social__label", labels.youtube);
      setIconLabel(".prof-social__item:nth-of-type(3) .prof-social__label", labels.facebook);
      setIconLabel(".prof-social__item:nth-of-type(4) .prof-social__label", labels.website);
      setIconLabel(".prof-social__item:nth-of-type(5) .prof-social__label", labels.whatsapp);
    }

    applyExtraStaticTranslations(file);

    document.documentElement.setAttribute("lang", currentLang);
  }




  /* ---------- Sede detail page ---------- */
  async function initSedeDetail() {
    const host = $("#sede-detail");
    if (!host) return;
    const slug = host.getAttribute("data-slug");
    const sedes = await loadJSON(localPath("data/sedes.json"));
    if (!sedes) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackSedes"))}</p>`;
      return;
    }
    const s = sedes.find(x => x.slug === slug);
    if (!s) {
      host.innerHTML = `<p class="fallback">${escapeHTML(t("common", "fallbackSedes"))}</p>`;
      return;
    }

    const titleEl = $("#sede-title");
    const subEl = $("#sede-sub");
    if (titleEl) titleEl.textContent = tv(s.name);
    if (subEl) subEl.textContent = `${tv(s.region)} · ${tv(s.country)}`;
    if (s.image) {
      const imgHost = $("#sede-image");
      if (imgHost) imgHost.innerHTML = `<img src="${escapeHTML(s.image)}" alt="${escapeHTML(tv(s.name))}" />`;
    }

    const scheduleHTML = s.schedule && s.schedule.length
      ? `<ul>${s.schedule.map(h => `<li><strong>${escapeHTML(tv(h.group))}</strong><span>${escapeHTML(tv(h.days))} · ${escapeHTML(tv(h.time))}</span></li>`).join("")}</ul>`
      : `<p class="placeholder">${escapeHTML(t("common", "schedulesPending"))}</p>`;

    const pricingHTML = s.pricing && s.pricing.length
      ? `<ul>${s.pricing.map(p => `<li><strong>${escapeHTML(tv(p.label))}</strong><span>${escapeHTML(tv(p.value))}</span></li>`).join("")}</ul>`
      : `<p class="placeholder">${escapeHTML(t("common", "pricesPending"))}</p>`;

    const contactsHTML = `
      <ul>
        <li><strong>${escapeHTML(t("common", "responsibleLabel"))}</strong><span>${escapeHTML(tv(s.responsible))}</span></li>
        <li><strong>${escapeHTML(t("common", "addressLabel"))}</strong><span>${escapeHTML(tv(s.address))}</span></li>
        ${s.whatsappDisplay ? `<li><strong>WhatsApp</strong><span>${escapeHTML(tv(s.whatsappDisplay))}</span></li>` : ""}
        ${s.instagramHandle ? `<li><strong>Instagram</strong><span>${escapeHTML(s.instagramHandle)}</span></li>` : ""}
        ${s.facebook ? `<li><strong>Facebook</strong><span>${escapeHTML(s.facebook)}</span></li>` : ""}
      </ul>
    `;

    const buttonsHTML = `
      <div class="btn-row" style="margin-top:1rem;">
        ${s.whatsapp
          ? `<a class="btn btn--primary" href="${escapeHTML(s.whatsapp)}" target="_blank" rel="noopener noreferrer" data-testid="sede-whatsapp-${escapeHTML(s.slug)}">${escapeHTML(t("common", "contactWhatsApp"))}</a>`
          : `<a class="btn btn--outline" href="${localPath("contacto.html")}" data-prefetch data-testid="sede-contact-${escapeHTML(s.slug)}">${escapeHTML(t("common", "generalContact"))}</a>`}
        ${s.instagram ? `<a class="btn btn--ghost" href="${escapeHTML(s.instagram)}" target="_blank" rel="noopener noreferrer" data-testid="sede-ig-${escapeHTML(s.slug)}">${escapeHTML(t("common", "instagram"))}</a>` : ""}
        <a class="btn btn--ghost" href="${localPath("sedes.html")}" data-prerender data-testid="sede-back-${escapeHTML(s.slug)}">${escapeHTML(t("common", "backToLocations"))}</a>
      </div>
    `;

    const content = $("#sede-content");
    if (content) {
      content.innerHTML = `
        <div class="info-block">
          <h3>${escapeHTML(t("common", "schedules"))}</h3>
          ${scheduleHTML}
        </div>
        <div class="info-block">
          <h3>${escapeHTML(t("common", "pricing"))}</h3>
          ${pricingHTML}
        </div>
        <div class="info-block">
          <h3>${escapeHTML(t("common", "contact"))}</h3>
          ${contactsHTML}
        </div>
        ${buttonsHTML}
      `;
    }
  }

  /* ---------- Init ---------- */
  function init() {
    document.documentElement.setAttribute("lang", currentLang);
    renderHeader();
    renderFooter();
    initHomeSedes();
    initSedesPage();
    initProfesoresPage();
    initGalleryPage();
    initEventosPage();
    initContactCards();
    initContactForm();
    initSedeDetail();
    translateStaticPage();
    initScrollReveal();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
