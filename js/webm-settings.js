/* This file is dedicated to the public domain; you may do as you wish with it. */

if (typeof _ == 'undefined') {
  var _ = function(a) { return a; };
}

// Default settings
var defaultSettings_webm = {
    "videoexpand": true,
    "videohover": false,
    "videovolume": 1.0
};

// Non-persistent settings for when localStorage is absent/disabled
var tempSettings_webm = {};

// Scripts obtain settings by calling this function
function setting_webm(name) {
    if (localStorage) {
        if (localStorage[name] === undefined) return defaultSettings_webm[name];
        return JSON.parse(localStorage[name]);
    } else {
        if (tempSettings_webm[name] === undefined) return defaultSettings_webm[name];
        return tempSettings_webm[name];
    }
}

// Settings should be changed with this function
function changeSetting_webm(name, value) {
    if (localStorage) {
        localStorage[name] = JSON.stringify(value);
    } else {
        tempSettings_webm[name] = value;
    }
}

// Create settings menu
var settingsMenu_webm = document.createElement("div");
var prefix = "", suffix = "", style = "";
if (window.Options) {
  var tab = Options.add_tab("webm", "video-camera", _("WebM"));
  $(settingsMenu_webm).appendTo(tab.content);
}
else {
  prefix = '<a class="unimportant" href="javascript:void(0)">'+_('WebM Settings')+'</a>';
  settingsMenu_webm.style.textAlign = "right";
  settingsMenu_webm.style.background = "inherit";
  suffix = '</div>';
  style = 'display: none; text-align: left; position: absolute; right: 1em; margin-left: -999em; margin-top: -1px; padding-top: 1px; background: inherit;';
}

settingsMenu_webm.innerHTML = prefix
    + '<div style="'+style+'">'
    + '<label><input type="checkbox" name="videoexpand">'+_('Expand videos inline')+'</label><br>'
    + '<label><input type="checkbox" name="videohover">'+_('Play videos on hover')+'</label><br>'
    + '<label><input type="range" name="videovolume" min="0" max="1" step="0.01" style="width: 4em; height: 1ex; vertical-align: middle; margin: 0px;">'+_('Default volume')+'</label><br>'
    + suffix;

function refreshSettings_webm() {
    var settingsItems_webm = settingsMenu_webm.getElementsByTagName("input");
    for (var i = 0; i < settingsItems_webm.length; i++) {
        var control = settingsItems_webm[i];
        if (control.type == "checkbox") {
            control.checked = setting_webm(control.name);
        } else if (control.type == "range") {
            control.value = setting_webm(control.name);
        }
    }
}

function setUpControl_webm(control) {
    if (control.addEventListener) control.addEventListener("change", function(e) {
        if (control.type == "checkbox") {
            changeSetting_webm(control.name, control.checked);
        } else if (control.type == "range") {
            changeSetting_webm(control.name, control.value);
        }
    }, false);
}

refreshSettings_webm();
var settingsItems_webm = settingsMenu_webm.getElementsByTagName("input");
for (var i = 0; i < settingsItems_webm.length; i++) {
    setUpControl(settingsItems_webm[i]);
}

if (settingsMenu_webm.addEventListener && !window.Options) {
    settingsMenu_webm.addEventListener("mouseover", function(e) {
        refreshSettings_webm();
        settingsMenu_webm.getElementsByTagName("a")[0].style.fontWeight = "bold";
        settingsMenu_webm.getElementsByTagName("div")[0].style.display = "block";
    }, false);
    settingsMenu_webm.addEventListener("mouseout", function(e) {
        settingsMenu_webm.getElementsByTagName("a")[0].style.fontWeight = "normal";
        settingsMenu_webm.getElementsByTagName("div")[0].style.display = "none";
    }, false);
}
