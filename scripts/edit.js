async function getEditEntryForm() {
  const body = {
    action: "load_edit_entry",
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
  console.log(data);
  // const data = await response.json();

  // if (data) {
  //   for (let item of data.entry_fields) {

  //     const parser = new DOMParser();
  //     const htmlDocument = parser.parseFromString(item.xml_content, "text/html");
  //     const section = htmlDocument.documentElement.querySelector(
  //       "#wpforms-edit-entry-form"
  //     );
  //     console.log(section);
  //   }
  // }
}

document.addEventListener("DOMContentLoaded", async function (event) {
  const formSetUp = await getEditEntryForm();
});
