(() => {
  const dataSources = ["data/site-data.json", "api/site-data.php"];
  const catalogGrid = document.getElementById("catalog-grid");
  const filtersWrap = document.getElementById("catalog-filters");
  const lookbookWrap = document.getElementById("lookbook-grid");
  const whatsappLink = document.getElementById("footer-whatsapp-link");
  const navWhatsappLink = document.getElementById("nav-whatsapp-link");

  if (!catalogGrid || !filtersWrap || !lookbookWrap) return;

  const state = {
    filter: "all",
    products: [],
    carousel: {},
    settings: {},
  };

  const formatPrice = (value) => `от ${Number(value || 0).toLocaleString("ru-RU")} ₸`;

  function productKeyword(collection) {
    const value = String(collection || "").toLowerCase();
    if (value.includes("anime")) return "Аниме футболка";
    if (value.includes("rock")) return "Рок футболка";
    return "Футболка с принтом";
  }

  function buildProductAlt(item, imageIndex, imageCount, context) {
    const title = String(item?.title || "TENJI Brand").trim();
    const keyword = productKeyword(item?.collection);
    const photo = imageCount > 1 ? `Фото ${imageIndex + 1} из ${imageCount}` : "Фото товара";
    const area = context === "lookbook" ? "Lookbook TENJI" : "Каталог TENJI";
    return `${keyword} TENJI Brand: ${title}. ${photo}. ${area}. Алматы.`;
  }

  function normalizePhone(value) {
    return String(value || "")
      .replace(/\D/g, "")
      .trim();
  }

  function applySettings(settings) {
    document.querySelectorAll("[data-cms]").forEach((el) => {
      const key = el.getAttribute("data-cms");
      if (!key) return;
      const value = settings[key];
      if (typeof value === "string" && value.length) {
        el.textContent = value;
      }
    });
    const phone = normalizePhone(settings.whatsapp_number || "+77080074162");
    if (whatsappLink) whatsappLink.href = `https://wa.me/${phone}`;
    if (navWhatsappLink) navWhatsappLink.href = `https://wa.me/${phone}`;
  }

  function collectionList() {
    const set = new Set(state.products.map((item) => item.collection || "Other"));
    return Array.from(set).sort((a, b) => a.localeCompare(b, "ru"));
  }

  function filteredProducts() {
    if (state.filter === "all") return state.products;
    return state.products.filter((item) => item.collection === state.filter);
  }

  function renderFilters() {
    filtersWrap.innerHTML = "";
    const btnAll = document.createElement("button");
    btnAll.type = "button";
    btnAll.className = `catalog-filter${state.filter === "all" ? " active" : ""}`;
    btnAll.textContent = "Все коллекции";
    btnAll.addEventListener("click", () => {
      state.filter = "all";
      renderAll();
    });
    filtersWrap.appendChild(btnAll);

    collectionList().forEach((name) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `catalog-filter${state.filter === name ? " active" : ""}`;
      btn.textContent = name;
      btn.addEventListener("click", () => {
        state.filter = name;
        renderAll();
      });
      filtersWrap.appendChild(btn);
    });
  }

  function waLinkForProduct(item) {
    const phone = normalizePhone(state.settings.whatsapp_number || "+77080074162");
    const text = `Здравствуйте! Хочу заказать ${item.title}. Цена: ${formatPrice(item.price)}. Размеры: ${(item.sizes || []).join(", ")}.`;
    return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`;
  }

  function renderCatalog() {
    catalogGrid.innerHTML = "";
    const items = filteredProducts();
    if (!items.length) {
      catalogGrid.innerHTML =
        '<article class="product-card"><h3>Каталог пуст</h3><p class="product-desc">Добавьте товары в файл data/site-data.json.</p></article>';
      return;
    }

    items.forEach((item) => {
      const images = Array.isArray(item.images) && item.images.length ? item.images : ["images/optimized/tenji-logo-320.png"];
      if (typeof state.carousel[item.id] !== "number") state.carousel[item.id] = 0;
      const idx = state.carousel[item.id] % images.length;

      const card = document.createElement("article");
      card.className = "product-card";

      const tag = document.createElement("div");
      tag.className = "product-tag anime";
      tag.textContent = item.collection || "Other";

      const media = document.createElement("div");
      media.className = "product-media";
      const img = document.createElement("img");
      img.src = images[idx];
      img.alt = buildProductAlt(item, idx, images.length, "catalog");
      img.loading = "lazy";
      img.decoding = "async";
      img.setAttribute("fetchpriority", "low");
      media.appendChild(img);

      if (images.length > 1) {
        const prev = document.createElement("button");
        prev.type = "button";
        prev.className = "carousel-btn prev";
        prev.textContent = "<";
        prev.addEventListener("click", (event) => {
          event.preventDefault();
          state.carousel[item.id] = (state.carousel[item.id] - 1 + images.length) % images.length;
          renderCatalog();
        });

        const next = document.createElement("button");
        next.type = "button";
        next.className = "carousel-btn next";
        next.textContent = ">";
        next.addEventListener("click", (event) => {
          event.preventDefault();
          state.carousel[item.id] = (state.carousel[item.id] + 1) % images.length;
          renderCatalog();
        });

        const counter = document.createElement("div");
        counter.className = "carousel-counter";
        counter.textContent = `${idx + 1}/${images.length}`;

        media.append(prev, next, counter);
      }

      const title = document.createElement("h3");
      title.textContent = item.title;
      const desc = document.createElement("p");
      desc.className = "product-desc";
      desc.textContent = `${item.description || "Стильная футболка TENJI."} Размеры: ${(item.sizes || []).join(", ")}.`;

      const meta = document.createElement("div");
      meta.className = "product-meta";
      const price = document.createElement("span");
      price.textContent = formatPrice(item.price);
      const wa = document.createElement("a");
      wa.className = "wa-button";
      wa.href = waLinkForProduct(item);
      wa.target = "_blank";
      wa.rel = "noopener noreferrer";
      wa.textContent = "Заказать в WhatsApp";
      meta.append(price, wa);

      card.append(tag, media, title, desc, meta);
      catalogGrid.appendChild(card);
    });
  }

  function renderLookbook() {
    lookbookWrap.innerHTML = "";
    filteredProducts().forEach((item) => {
      const images = Array.isArray(item.images) && item.images.length ? item.images : ["images/optimized/tenji-logo-320.png"];

      images.forEach((source, imageIndex) => {
        const figure = document.createElement("figure");
        const img = document.createElement("img");
        img.src = source;
        img.alt = buildProductAlt(item, imageIndex, images.length, "lookbook");
        img.loading = "lazy";
        img.decoding = "async";
        img.setAttribute("fetchpriority", "low");
        figure.appendChild(img);
        lookbookWrap.appendChild(figure);
      });
    });
  }

  function renderAll() {
    renderFilters();
    renderCatalog();
    renderLookbook();
  }

  async function loadSiteData() {
    for (const source of dataSources) {
      try {
        const res = await fetch(source, { credentials: "same-origin" });
        if (!res.ok) continue;
        const json = await res.json();
        if (!json || (typeof json.ok !== "undefined" && !json.ok)) continue;
        return json;
      } catch (_err) {
        // Try the next source.
      }
    }
    throw new Error("Failed to load site data");
  }

  async function init() {
    try {
      const json = await loadSiteData();
      state.products = Array.isArray(json.products) ? json.products : [];
      state.settings = typeof json.settings === "object" && json.settings ? json.settings : {};
      applySettings(state.settings);
      renderAll();
    } catch (_err) {
      catalogGrid.innerHTML =
        '<article class="product-card"><h3>Ошибка загрузки</h3><p class="product-desc">Не удалось загрузить данные. Проверьте, что существует файл data/site-data.json.</p></article>';
    }
  }

  init();
})();
