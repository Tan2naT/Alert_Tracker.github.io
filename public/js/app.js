const tableBody = document.getElementById("coordinateTable");
const mapFrame = document.getElementById("mapFrame");
const selectedCoordinates = document.getElementById("selectedCoordinates");
const latestLocation = document.getElementById("latestLocation");

let coordinateData = [];
let selectedCoordinateId = null;

function formatCoordinates(location) {
  return `${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`;
}

function getDeviceSource(location) {
  return location.deviceSource || location.source || "Unknown Device";
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

function renderTable() {
  tableBody.innerHTML = "";

  coordinateData.forEach((location, index) => {
    const row = document.createElement("tr");
    const numberCell = document.createElement("td");
    const deviceCell = document.createElement("td");
    const deviceFields = document.createElement("div");
    const deviceLabel = document.createElement("strong");
    const deviceSource = document.createElement("span");
    const coordinatesCell = document.createElement("td");
    const timeCell = document.createElement("td");
    const statusCell = document.createElement("td");
    const statusBadge = document.createElement("span");
    const status = location.deviceStatus || location.status || "Idle";

    numberCell.textContent = index + 1;
    deviceFields.className = "dashboard-device";
    deviceLabel.textContent = location.deviceLabel || "Device";
    deviceSource.textContent = getDeviceSource(location);
    deviceFields.append(deviceLabel, deviceSource);
    deviceCell.appendChild(deviceFields);
    coordinatesCell.className = "coordinates";
    coordinatesCell.textContent = formatCoordinates(location);
    timeCell.textContent = location.addedAt;
    statusBadge.className = `badge ${status.toLowerCase()}`;
    statusBadge.textContent = status;
    statusCell.appendChild(statusBadge);

    row.append(numberCell, deviceCell, coordinatesCell, timeCell, statusCell);

    if (location.id === selectedCoordinateId) {
      row.classList.add("active");
    }

    row.addEventListener("click", () => selectRow(index));
    tableBody.appendChild(row);
  });

  if (coordinateData.length === 0) {
    tableBody.innerHTML = '<tr><td colspan="5">No coordinates available yet.</td></tr>';
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
  }

  renderTable();
}

loadCoordinates();
setInterval(loadCoordinates, 5000);
