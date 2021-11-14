async function getEditEntryForm() {
  const body = {
    action: "load_edit_entries",
    nonce: ajax_var.nonce,
    form_id: ajax_var.form_id,
  };

  const response = await fetch(ajax_var.url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams(body),
  });
  const data = await response.text();
  const container = document.querySelector(`#${ajax_var.id}`);
  if (data && container) {
    console.log(data);
    container.querySelector(".table-container").innerHTML = data;
    const rows = container.querySelectorAll("tbody tr");
    for (let item of rows) {
      const body2 = {
        action: "load_entry_form",
        nonce: ajax_var.nonce,
        form_id: ajax_var.form_id,
        page: "wpforms-entries",
        view: "edit",
      };
      item.addEventListener("click", async function () {
        const response2 = await fetch(ajax_var.url, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            entry_id: item.className.split("eid-")[1],
            ...body2,
          }),
        });
        const data2 = await response2.text();

        if (data2) {
          // const parser = new DOMParser();
          // const htmlDocument = parser.parseFromString(data2, "text/html");
          // const section = htmlDocument.documentElement.querySelector("#wpwrap");
          container.querySelector(".form-container").innerHTML = data2;
        }
      });
    }
  }
}

document.addEventListener("DOMContentLoaded", async function (event) {
  const formSetUp = await getEditEntryForm();
});
