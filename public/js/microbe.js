$(function(){
    let microbeId = $("#data-holder").attr("data-microbe-id")

    $("#new-metadata-button").click(function() {
        $("#new-metadata-button").hide()
        $("#new-metadata-form").show()

        $.get(
            '/api/metadata/load-missing/' + microbeId,
            function (response) {
                let optionsHtml = ''
                if (response.length === 0) {
                    optionsHtml += "<option value='X' disabled selected hidden>Žádná další metadata nejsou dostupná.</option>"
                } else {
                    optionsHtml += "<option value='X' disabled selected hidden>Vyberte typ metadat</option>"
                }
                response.forEach(function (metadataOption) {
                    optionsHtml += "<option value='" + metadataOption.id + "'>" + metadataOption.name + " (" + metadataOption.datatype + ")</option>\n"
                })
                $("#new-metadata-key").html(optionsHtml);
            }
        )
    })

    $("#new-metadata-key").change(function() {
        let keyId = $("#new-metadata-key").val();

        $.get(
            '/api/metadata/load-value-structure/' + keyId,
            function (response) {
                const html = generateInputStructure(response) + '<input type="submit" value="Uložit">';
                $("#new-metadata-value").html(html);
            }
        )
    })
});

function generateInputStructure(response, keyIdPrefix = '') {
    const type = response.type;
    const keyId = response.keyId;
    const multipleValues = response.multipleValues; //TODO: add support
    const controls = response.controls;
    const tag = controls.tag;
    const requiresClosing = controls.requiresClosing;
    const attributes = controls.attributes;
    let html = '';

    //Generating opening tag
    html += '<' + tag;
    for (const key in attributes) {
        html += ' ' + key + '="' + attributes[key] + '"';
    }
    html += ' name="' + keyId + '">\n';

    switch (type) {
        case 'primitive':
            //No control inner HTML needed yet
            break;
        case 'enum':
            const options = controls.options;
            for (const key in options) {
                html += '<option value="' + key + '">' + options[key] + '</option>\n';
            }
            break;
        case 'object':
            const parts = controls.parts;
            parts.forEach(function (part){
                html += generateInputStructure(part);
            });
            break;
    }

    //Generating closing tag
    if (requiresClosing) {
        html += '</' + tag + '>\n';
    }

    return html;
}