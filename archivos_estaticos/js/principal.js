// Simulación de política de privacidad
function openPrivacyPolicy() {
  const privacyHTML = `
           
            `;

  const win = window.open("", "_blank", "width=1000,height=700,scrollbars=yes");
  win.document.write(privacyHTML);
  win.document.close();
}
