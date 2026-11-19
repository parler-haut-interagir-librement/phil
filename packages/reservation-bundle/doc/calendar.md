# The calendar

When `phil/calendar-bundle` is installed and `calendar` is on, the confirmed reservations are items of its views,
from the item source `ReservationItems`. Each item is titled and tagged with its resource's name, so that the tag
columns of the day view lay the resources side by side; it is a time, never a whole day, and its source is
`reservation`. Holds and cancelled reservations are left out, and the holder is never shown.

Each period shows at most `batch` reservations. Turn the source off with `calendar: false`.
