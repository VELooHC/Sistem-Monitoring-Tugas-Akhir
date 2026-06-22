const togglePasswordButton = document.querySelector(".toggle-password");
const passwordInput = document.querySelector("#password");

if (togglePasswordButton && passwordInput) {
    togglePasswordButton.addEventListener("click", function () {
        const isHidden = passwordInput.type === "password";
        passwordInput.type = isHidden ? "text" : "password";
        togglePasswordButton.setAttribute(
            "aria-label",
            isHidden ? "Sembunyikan password" : "Tampilkan password"
        );
    });
}
