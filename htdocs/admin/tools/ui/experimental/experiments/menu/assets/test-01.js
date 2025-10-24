document.addEventListener("DOMContentLoaded", () => {
	const burgerBtn = document.getElementById("burger-btn");
	const body = document.body;

	const modes = ["default", "minimized", "full-open"];
	let currentModeIndex = 0;

	function applyMode(mode) {
		// Supprime toutes les classes de mode
		modes.forEach(m => body.classList.remove(m));
		body.classList.add(mode);
	}

	// Clique sur le burger pour changer le mode
	burgerBtn.addEventListener("click", () => {
		currentModeIndex = (currentModeIndex + 1) % modes.length;
		applyMode(modes[currentModeIndex]);
	});

	// Mode initial
	applyMode(modes[currentModeIndex]);
});
