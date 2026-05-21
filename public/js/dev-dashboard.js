const tableBody = document.getElementById("coordinateTable");
const mapFrame = document.getElementById("mapFrame");
const selectedCoordinates = document.getElementById("selectedCoordinates");
const latestLocation = document.getElementById("latestLocation");
const developerDashboardKey = window.DEVELOPER_DASHBOARD_KEY || "";

let coordinateData = [];
let selectedCoordinateId = null;

function formatCoordinates(location) {
  return `${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`;
}

function getDeviceTrail(location) {
  return Array.isArray(location.deviceTrail) ? location.deviceTrail : [location];
}

function getMapUrl(location) {
  const trail = getDeviceTrail(location).slice(-10);

  if (trail.length > 1) {
    const searchParams = new URLSearchParams({
      saddr: formatCoordinates(trail[0]),
      daddr: trail.slice(1).map(point => formatCoordinates(point)).join(" to:"),
      output: "embed",
    });

    return `https://maps.google.com/maps?${searchParams.toString()}`;
  }

  const coordinates = formatCoordinates(location);

  return `https://maps.google.com/maps?q=${encodeURIComponent(coordinates)}&z=16&output=embed`;
}

function loadMap(location) {
  const coordinates = formatCoordinates(location);

  mapFrame.src = getMapUrl(location);
  selectedCoordinates.textContent = `${location.deviceLabel || "Device"}: ${coordinates}`;
}

function selectRow(index) {
  const rows = document.querySelectorAll("tbody tr");
  const location = coordinateData[index];

  if (!location) {
    return;
  }

  rows.forEach(row => row.classList.remove("active"));

  if (rows[index]) {
    rows[index].classList.add("active");
  }

  selectedCoordinateId = location.id;
  loadMap(location);
}

function createInput(value, className, type = "text") {
  const input = document.createElement("input");

  input.className = className;
  input.type = type;
  input.value = value;

  if (type === "number") {
    input.step = "any";
  }

  return input;
}

function buildActionButton(label, className) {
  const button = document.createElement("button");

  button.type = "button";
  button.className = className;
  button.textContent = label;

  return button;
}

async function saveCoordinate(index, row) {
  const location = coordinateData[index];
  const latitude = row.querySelector("[data-field='latitude']").value;
  const longitude = row.querySelector("[data-field='longitude']").value;
  const source = row.querySelector("[data-field='source']").value;
  const addedAt = row.querySelector("[data-field='addedAt']").value;
  const status = location.deviceStatus || "Idle";

  const response = await fetch(
    `../../api/coordinates.php?id=${encodeURIComponent(location.id)}&devKey=${encodeURIComponent(developerDashboardKey)}`,
    {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        id: location.id,
        latitude,
        longitude,
        source,
        addedAt,
        status,
      }),
    }
  );

  if (!response.ok) {
    throw new Error("Unable to save coordinate.");
  }

  await loadCoordinates();
}

async function deleteCoordinate(index) {
  const location = coordinateData[index];
  const confirmed = window.confirm(`Delete coordinate ${formatCoordinates(location)}?`);

  if (!confirmed) {
    return;
  }

  const response = await fetch(
    `../../api/coordinates.php?id=${encodeURIComponent(location.id)}&devKey=${encodeURIComponent(developerDashboardKey)}`,
    {
      method: "DELETE",
    }
  );

  if (!response.ok) {
    throw new Error("Unable to delete coordinate.");
  }

  await loadCoordinates();
}

function renderTable() {
  tableBody.innerHTML = "";

  coordinateData.forEach((location, index) => {
    const row = document.createElement("tr");
    const numberCell = document.createElement("td");
    const deviceCell = document.createElement("td");
    const coordinatesCell = document.createElement("td");
    const timeCell = document.createElement("td");
    const statusCell = document.createElement("td");
    const actionsCell = document.createElement("td");
    const coordinateFields = document.createElement("div");
    const deviceFields = document.createElement("div");
    const deviceLabel = document.createElement("strong");
    const sourceInput = createInput(location.deviceSource || location.source || "ESP32", "developer-input");
    const latitudeInput = createInput(location.latitude, "developer-input coordinate-input", "number");
    const longitudeInput = createInput(location.longitude, "developer-input coordinate-input", "number");
    const timeInput = createInput(location.addedAt || "", "developer-input");
    const status = location.deviceStatus || location.status || "Idle";
    const statusBadge = document.createElement("span");
    const saveButton = buildActionButton("Save", "action-button save-button");
    const deleteButton = buildActionButton("Delete", "action-button delete-button");

    sourceInput.dataset.field = "source";
    latitudeInput.dataset.field = "latitude";
    longitudeInput.dataset.field = "longitude";
    timeInput.dataset.field = "addedAt";

    numberCell.textContent = index + 1;
    deviceFields.className = "developer-device";
    deviceLabel.textContent = location.deviceLabel || "Device";
    sourceInput.title = "Device source";
    deviceFields.append(deviceLabel, sourceInput);
    deviceCell.appendChild(deviceFields);
    coordinateFields.className = "developer-coordinates";
    coordinateFields.append(latitudeInput, longitudeInput);
    coordinatesCell.appendChild(coordinateFields);
    timeCell.appendChild(timeInput);
    statusBadge.className = `badge ${status.toLowerCase()}`;
    statusBadge.textContent = status;
    statusCell.appendChild(statusBadge);
    actionsCell.className = "action-cell";
    actionsCell.append(saveButton, deleteButton);

    row.append(numberCell, deviceCell, coordinatesCell, timeCell, statusCell, actionsCell);

    if (location.id === selectedCoordinateId) {
      row.classList.add("active");
    }

    row.addEventListener("click", event => {
      if (event.target.closest("input, select, button")) {
        return;
      }

      selectRow(index);
    });

    saveButton.addEventListener("click", async () => {
      try {
        saveButton.disabled = true;
        saveButton.textContent = "Saving";
        await saveCoordinate(index, row);
      } catch (error) {
        latestLocation.textContent = error.message;
        saveButton.disabled = false;
        saveButton.textContent = "Save";
      }
    });

    deleteButton.addEventListener("click", async () => {
      try {
        deleteButton.disabled = true;
        deleteButton.textContent = "Deleting";
        await deleteCoordinate(index);
      } catch (error) {
        latestLocation.textContent = error.message;
        deleteButton.disabled = false;
        deleteButton.textContent = "Delete";
      }
    });

    tableBody.appendChild(row);
  });

  if (coordinateData.length === 0) {
    tableBody.innerHTML = '<tr><td colspan="6">No coordinates available yet.</td></tr>';
    latestLocation.textContent = "No latest location";
    selectedCoordinates.textContent = "";
    mapFrame.removeAttribute("src");
    selectedCoordinateId = null;
    return;
  }

  latestLocation.textContent = `${coordinateData[0].deviceLabel || "Device"}: ${formatCoordinates(coordinateData[0])} (${coordinateData[0].deviceStatus || "Idle"})`;

  if (selectedCoordinateId !== null && !coordinateData.some(location => location.id === selectedCoordinateId)) {
    selectedCoordinateId = null;
    selectedCoordinates.textContent = "";
    mapFrame.removeAttribute("src");
  }
}

async function loadCoordinates() {
  try {
    const response = await fetch("../../api/coordinates.php");

    if (!response.ok) {
      throw new Error("Coordinate API unavailable");
    }

    const data = await response.json();
    coordinateData = data.coordinates || [];
  } catch (error) {
    coordinateData = [];
    latestLocation.textContent = error.message;
  }

  renderTable();
}

loadCoordinates();
