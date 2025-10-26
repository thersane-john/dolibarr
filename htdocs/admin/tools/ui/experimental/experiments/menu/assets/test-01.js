document.addEventListener("DOMContentLoaded", () => {
	const burgerBtn = document.getElementById("burger-btn");
	const body = document.body;

	// Liste des modes disponibles
	const modes = ["menu-reduced", "menu-default", "menu-full-open", "menu-minimized"];

	// Récupère le dernier mode enregistré (ou "menu-default" par défaut)
	let savedMode = localStorage.getItem("menuMode") || "menu-default";
	let currentModeIndex = modes.indexOf(savedMode);
	if (currentModeIndex === -1) currentModeIndex = 0;

	function applyMode(mode) {
		// Supprime toutes les classes de mode
		modes.forEach(m => body.classList.remove(m));
		// Ajoute le nouveau mode
		body.classList.add(mode);
		// Sauvegarde le mode choisi
		localStorage.setItem("menuMode", mode);
	}

	// Applique le mode au chargement
	applyMode(modes[currentModeIndex]);

	// Clique sur le burger pour changer de mode
	burgerBtn.addEventListener("click", () => {
		currentModeIndex = (currentModeIndex + 1) % modes.length;
		applyMode(modes[currentModeIndex]);
	});
});


// document.addEventListener("DOMContentLoaded", () => {
//
// 	// --- Gestion ergonomique des sous-menus ---
// 	let openTimeout;
// 	let currentParent = null;
//
// 	document.querySelectorAll("#left-menu .left-menu__item").forEach(item => {
// 		item.addEventListener("mouseenter", () => {
// 			clearTimeout(openTimeout);
// 			openTimeout = setTimeout(() => {
// 				if (currentParent && currentParent !== item) {
// 					currentParent.classList.remove("open");
// 				}
// 				item.classList.add("open");
// 				currentParent = item;
// 			}, 250); // délai d’ouverture
// 		});
//
// 		item.addEventListener("mouseleave", () => {
// 			clearTimeout(openTimeout);
// 			openTimeout = setTimeout(() => {
// 				item.classList.remove("open");
// 				if (currentParent === item) currentParent = null;
// 			}, 300); // petit délai pour permettre le mouvement vers le sous-menu
// 		});
// 	});
// });

/** AVEC DETECTION DE DIRECTION  */
document.addEventListener("DOMContentLoaded", () => {

	// --- Sous-menus intelligents ---
	let currentParent = null;
	let timeoutId = null;
	let lastMousePos = { x: 0, y: 0 };

	// Détecte si c'est tactile
	const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

	// Suivi de la position de la souris (uniquement si pas tactile)
	if (!isTouchDevice) {
		document.addEventListener("mousemove", e => {
			lastMousePos = { x: e.pageX, y: e.pageY };
		});
	}

	const items = document.querySelectorAll("#left-menu .left-menu__item");

	function getBounds(el) {
		return el.getBoundingClientRect();
	}

	function shouldDelay(oldItem, newItem, mouse) {
		if (!oldItem) return false;
		const oldRect = getBounds(oldItem.querySelector(".left-sub-menu-parent"));
		if (!oldRect) return false;
		const topLeft = { x: oldRect.left, y: oldRect.top };
		const bottomLeft = { x: oldRect.left, y: oldRect.bottom };
		const slope1 = (topLeft.y - mouse.y) / (topLeft.x - mouse.x);
		const slope2 = (bottomLeft.y - mouse.y) / (bottomLeft.x - mouse.x);
		return slope1 * slope2 < 0;
	}

	items.forEach(item => {
		const activateMenu = () => {
			if (timeoutId) clearTimeout(timeoutId);

			const delay = (!isTouchDevice && shouldDelay(currentParent, item, lastMousePos)) ? 300 : 0;
			timeoutId = setTimeout(() => {
				if (currentParent && currentParent !== item) {
					currentParent.classList.remove("open");
				}
				item.classList.add("open");
				currentParent = item;
			}, delay);
		};

		const deactivateMenu = () => {
			if (timeoutId) clearTimeout(timeoutId);
			timeoutId = setTimeout(() => {
				item.classList.remove("open");
				if (currentParent === item) currentParent = null;
			}, 300);
		};

		if (isTouchDevice) {
			// Sur tactile, on ouvre/ferme au clic/tap
			item.addEventListener("click", e => {
				e.stopPropagation(); // évite propagation vers parent
				if (item.classList.contains("open")) {
					item.classList.remove("open");
					currentParent = null;
				} else {
					if (currentParent && currentParent !== item) {
						currentParent.classList.remove("open");
					}
					item.classList.add("open");
					currentParent = item;
				}
			});
		} else {
			// Sur desktop, hover intelligent
			item.addEventListener("mouseenter", activateMenu);
			item.addEventListener("mouseleave", deactivateMenu);
		}
	});

	// Sur mobile, fermer le menu si on clique en dehors
	if (isTouchDevice) {
		document.addEventListener("click", () => {
			if (currentParent) {
				currentParent.classList.remove("open");
				currentParent = null;
			}
		});
	}
});

/**
 * Le menu #left-menu continue à scroller librement tant qu’il a du contenu.

Si on atteint le haut ou le bas du menu et que la molette continue, alors le scroll n’est pas propagé au body.

Pas de overflow: hidden, donc aucun saut de mise en page.
 */
document.addEventListener("DOMContentLoaded", () => {
	const leftMenu = document.getElementById("left-menu");

	leftMenu.addEventListener("wheel", e => {
		// On récupère la position de scroll du menu
		const atTop = leftMenu.scrollTop === 0;
		const atBottom = leftMenu.scrollHeight - leftMenu.scrollTop === leftMenu.clientHeight;

		// Si on veut scroller vers le haut alors qu'on est déjà en haut
		// ou vers le bas alors qu'on est déjà en bas → empêche le scroll global
		if ((atTop && e.deltaY < 0) || (atBottom && e.deltaY > 0)) {
			e.preventDefault();
		}
	}, { passive: false });
});
