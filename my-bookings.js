const trips = [
  {
    id: 1,
    route: "Arafat → Muzdalifah",
    date: "2026-03-28",
    time: "17:30",
    bus: "Bus-07",
    availableSeats: 10
  },
  {
    id: 2,
    route: "Mina → Arafat",
    date: "2026-03-20",
    time: "14:00",
    bus: "Bus-03",
    availableSeats: 5
  },
  {
    id: 3,
    route: "Makkah → Mina",
    date: "2026-03-30",
    time: "08:15",
    bus: "Bus-10",
    availableSeats: 7
  }
];

const bookings = [
  {
    id: 101,
    tripId: 1,
    ref: "BK-MN37SC0",
    route: "Arafat → Muzdalifah",
    date: "2026-03-28",
    time: "17:30",
    bus: "Bus-07",
    status: "Confirmed"
  },
  {
    id: 102,
    tripId: 2,
    ref: "BK-MN36RDJ5",
    route: "Mina → Arafat",
    date: "2026-03-20",
    time: "14:00",
    bus: "Bus-03",
    status: "Confirmed"
  },
  {
    id: 103,
    tripId: 3,
    ref: "BK-MN1MPRLJ5",
    route: "Makkah → Mina",
    date: "2026-03-18",
    time: "09:00",
    bus: "Bus-10",
    status: "Cancelled"
  }
];

const bookingsList = document.getElementById("bookingsList");
const alertBox = document.getElementById("alertBox");
const cancelModal = document.getElementById("cancelModal");
const closeModal = document.getElementById("closeModal");
const keepBookingBtn = document.getElementById("keepBookingBtn");
const confirmCancelBtn = document.getElementById("confirmCancelBtn");

let selectedBookingId = null;

function renderBookings() {
  bookingsList.innerHTML = "";

  if (bookings.length === 0) {
    bookingsList.innerHTML = `
      <div class="empty-message">
        No bookings found.
      </div>
    `;
    return;
  }

  bookings.forEach((booking) => {
    const hasDeparted = isDeparturePassed(booking.date, booking.time);
    const isCancelled = booking.status.toLowerCase() === "cancelled";

    const card = document.createElement("div");
    card.className = "booking-card";

    card.innerHTML = `
      <div class="booking-info">
        <div class="booking-ref">Booking Ref: ${booking.ref}</div>

        <span class="status ${isCancelled ? "cancelled" : "confirmed"}">
          ${booking.status}
        </span>

        <div class="trip-details">
          <span><i class="fa-solid fa-location-dot"></i> ${booking.route}</span>
          <span><i class="fa-regular fa-calendar"></i> ${formatDate(booking.date)}</span>
          <span><i class="fa-regular fa-clock"></i> ${booking.time}</span>
          <span><i class="fa-solid fa-bus"></i> ${booking.bus}</span>
        </div>
      </div>

      <div class="booking-actions">
        <button class="icon-btn" title="View QR">
          <i class="fa-solid fa-qrcode"></i>
        </button>

        <button class="icon-btn" title="View Details">
          <i class="fa-regular fa-eye"></i>
        </button>

        <button
          class="icon-btn cancel-btn"
          title="${getCancelTitle(isCancelled, hasDeparted)}"
          data-id="${booking.id}"
          ${isCancelled || hasDeparted ? "disabled" : ""}
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    `;

    bookingsList.appendChild(card);
  });

  attachCancelEvents();
}

function attachCancelEvents() {
  const cancelButtons = document.querySelectorAll(".cancel-btn");

  cancelButtons.forEach((button) => {
    button.addEventListener("click", () => {
      selectedBookingId = Number(button.dataset.id);
      openModal();
    });
  });
}

function isDeparturePassed(date, time) {
  const departureDateTime = new Date(`${date}T${time}`);
  const now = new Date();
  return now >= departureDateTime;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toDateString();
}

function getCancelTitle(isCancelled, hasDeparted) {
  if (isCancelled) {
    return "Booking already cancelled";
  }

  if (hasDeparted) {
    return "Cancellation is not allowed after departure time";
  }

  return "Cancel booking";
}

function openModal() {
  cancelModal.classList.remove("hidden");
}

function hideModal() {
  cancelModal.classList.add("hidden");
  selectedBookingId = null;
}

function showAlert(message, type) {alertBox.textContent = message;
  alertBox.className = alert ${type};
  alertBox.classList.remove("hidden");

  setTimeout(() => {
    alertBox.classList.add("hidden");
  }, 3000);
}

confirmCancelBtn.addEventListener("click", () => {
  const booking = bookings.find((b) => b.id === selectedBookingId);

  if (!booking) {
    hideModal();
    return;
  }

  if (booking.status === "Cancelled") {
    showAlert("This booking is already cancelled.", "error");
    hideModal();
    return;
  }

  if (isDeparturePassed(booking.date, booking.time)) {
    showAlert("Cancellation is not allowed after departure time.", "error");
    hideModal();
    return;
  }

  booking.status = "Cancelled";

  const trip = trips.find((t) => t.id === booking.tripId);
  if (trip) {
    trip.availableSeats++;
  }

  renderBookings();
  showAlert("Booking cancelled successfully. Seat is available again.", "success");
  hideModal();
});

closeModal.addEventListener("click", hideModal);
keepBookingBtn.addEventListener("click", hideModal);

window.addEventListener("click", (event) => {
  if (event.target === cancelModal) {
    hideModal();
  }
});

renderBookings();
