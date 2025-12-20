import { useState } from "react";
import { mockCalendarEvents, CalendarEvent } from "../data/calendarData";
import { Card } from "./ui/card";
import { Button } from "./ui/button";
import { Badge } from "./ui/badge";
import { 
  Calendar as CalendarIcon, 
  Plus, 
  ChevronLeft, 
  ChevronRight,
  Bell,
  Clock,
  Briefcase,
  Users,
  Flag,
  CheckCircle2,
  Circle,
  Filter,
  Search
} from "lucide-react";
import { Input } from "./ui/input";

const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const monthNames = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

const typeIcons = {
  deadline: Clock,
  meeting: Users,
  reminder: Bell,
  milestone: Flag
};

const typeColors = {
  deadline: "bg-red-100 text-red-700 border-red-200",
  meeting: "bg-blue-100 text-blue-700 border-blue-200",
  reminder: "bg-purple-100 text-purple-700 border-purple-200",
  milestone: "bg-green-100 text-green-700 border-green-200"
};

const priorityColors = {
  high: "border-l-4 border-l-red-500",
  medium: "border-l-4 border-l-yellow-500",
  low: "border-l-4 border-l-gray-400"
};

export function CalendarView() {
  const [currentDate, setCurrentDate] = useState(new Date(2025, 11, 19)); // Dec 19, 2025
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [view, setView] = useState<"month" | "list">("month");
  const [events] = useState<CalendarEvent[]>(mockCalendarEvents);
  const [showEventModal, setShowEventModal] = useState(false);

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();

  const firstDayOfMonth = new Date(year, month, 1);
  const lastDayOfMonth = new Date(year, month + 1, 0);
  const startingDayOfWeek = firstDayOfMonth.getDay();
  const daysInMonth = lastDayOfMonth.getDate();

  const previousMonth = () => {
    setCurrentDate(new Date(year, month - 1, 1));
  };

  const nextMonth = () => {
    setCurrentDate(new Date(year, month + 1, 1));
  };

  const getEventsForDate = (day: number) => {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return events.filter(event => event.date === dateStr);
  };

  const handleDateClick = (day: number) => {
    const date = new Date(year, month, day);
    setSelectedDate(date);
  };

  const handleCreateEvent = () => {
    setShowEventModal(true);
    alert("Create Event modal would open here with form for: Title, Date, Time, Type, Project, Reminders");
  };

  // Generate calendar days
  const calendarDays = [];
  
  // Empty cells before first day
  for (let i = 0; i < startingDayOfWeek; i++) {
    calendarDays.push(null);
  }
  
  // Days of the month
  for (let day = 1; day <= daysInMonth; day++) {
    calendarDays.push(day);
  }

  return (
    <div className="h-full flex flex-col bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">Calendar & Reminders</h1>
            <p className="text-gray-600 mt-1">Track deadlines, meetings, and milestones</p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant={view === "month" ? "default" : "outline"}
              onClick={() => setView("month")}
            >
              Month
            </Button>
            <Button
              variant={view === "list" ? "default" : "outline"}
              onClick={() => setView("list")}
            >
              List
            </Button>
            <Button onClick={handleCreateEvent} className="bg-blue-600 hover:bg-blue-700">
              <Plus className="w-4 h-4 mr-2" />
              New Event
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <Input 
            placeholder="Search events..." 
            className="pl-9"
          />
        </div>
      </div>

      {view === "month" ? (
        /* Month View */
        <div className="flex-1 overflow-hidden">
          <div className="grid grid-cols-1 lg:grid-cols-3 h-full">
            {/* Calendar Grid */}
            <div className="lg:col-span-2 border-r border-gray-200 p-6 overflow-y-auto">
              {/* Month Navigation */}
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl font-semibold text-gray-900">
                  {monthNames[month]} {year}
                </h2>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" onClick={previousMonth}>
                    <ChevronLeft className="w-4 h-4" />
                  </Button>
                  <Button variant="outline" size="sm" onClick={() => setCurrentDate(new Date())}>
                    Today
                  </Button>
                  <Button variant="outline" size="sm" onClick={nextMonth}>
                    <ChevronRight className="w-4 h-4" />
                  </Button>
                </div>
              </div>

              {/* Calendar Grid */}
              <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                {/* Day Headers */}
                <div className="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                  {daysOfWeek.map(day => (
                    <div key={day} className="p-2 text-center text-sm font-medium text-gray-600">
                      {day}
                    </div>
                  ))}
                </div>

                {/* Calendar Days */}
                <div className="grid grid-cols-7">
                  {calendarDays.map((day, index) => {
                    const dayEvents = day ? getEventsForDate(day) : [];
                    const isToday = day && 
                      day === 19 && 
                      month === 11 && 
                      year === 2025;
                    const isSelected = selectedDate &&
                      day === selectedDate.getDate() &&
                      month === selectedDate.getMonth() &&
                      year === selectedDate.getFullYear();

                    return (
                      <div
                        key={index}
                        onClick={() => day && handleDateClick(day)}
                        className={`
                          min-h-24 p-2 border-b border-r border-gray-200
                          ${day ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-50'}
                          ${isToday ? 'bg-blue-50' : ''}
                          ${isSelected ? 'ring-2 ring-blue-500' : ''}
                        `}
                      >
                        {day && (
                          <>
                            <div className={`
                              text-sm font-medium mb-1
                              ${isToday ? 'text-blue-600' : 'text-gray-900'}
                            `}>
                              {day}
                            </div>
                            <div className="space-y-1">
                              {dayEvents.slice(0, 2).map(event => {
                                const Icon = typeIcons[event.type];
                                return (
                                  <div
                                    key={event.id}
                                    className={`
                                      text-xs px-1.5 py-1 rounded truncate
                                      ${typeColors[event.type]}
                                      ${event.completed ? 'opacity-50 line-through' : ''}
                                    `}
                                  >
                                    <div className="flex items-center gap-1">
                                      <Icon className="w-3 h-3 flex-shrink-0" />
                                      <span className="truncate">{event.title}</span>
                                    </div>
                                  </div>
                                );
                              })}
                              {dayEvents.length > 2 && (
                                <div className="text-xs text-gray-500 px-1.5">
                                  +{dayEvents.length - 2} more
                                </div>
                              )}
                            </div>
                          </>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* Sidebar - Upcoming Events */}
            <div className="bg-gray-50 overflow-y-auto p-6">
              <h3 className="font-semibold text-gray-900 mb-4">Upcoming Events</h3>
              <div className="space-y-3">
                {events
                  .filter(e => !e.completed)
                  .sort((a, b) => a.date.localeCompare(b.date))
                  .slice(0, 10)
                  .map(event => {
                    const Icon = typeIcons[event.type];
                    return (
                      <Card 
                        key={event.id}
                        className={`p-4 bg-white border ${priorityColors[event.priority]}`}
                      >
                        <div className="flex items-start justify-between mb-2">
                          <Badge className={typeColors[event.type]}>
                            <Icon className="w-3 h-3 mr-1" />
                            {event.type}
                          </Badge>
                          {event.priority === "high" && (
                            <Badge variant="destructive">High</Badge>
                          )}
                        </div>
                        <h4 className="font-medium text-gray-900 text-sm mb-1">
                          {event.title}
                        </h4>
                        {event.description && (
                          <p className="text-xs text-gray-600 mb-2">{event.description}</p>
                        )}
                        <div className="flex items-center gap-2 text-xs text-gray-500">
                          <CalendarIcon className="w-3 h-3" />
                          <span>{new Date(event.date).toLocaleDateString()}</span>
                          {event.time && (
                            <>
                              <span>•</span>
                              <Clock className="w-3 h-3" />
                              <span>{event.time}</span>
                            </>
                          )}
                        </div>
                        {event.project && (
                          <div className="flex items-center gap-1 text-xs text-blue-600 mt-2">
                            <Briefcase className="w-3 h-3" />
                            <span className="font-mono">{event.project}</span>
                          </div>
                        )}
                        {event.reminders && event.reminders.length > 0 && (
                          <div className="mt-2 pt-2 border-t border-gray-200">
                            <div className="flex items-center gap-1 text-xs text-gray-600">
                              <Bell className="w-3 h-3" />
                              <span>
                                {event.reminders.filter(r => r.enabled).length} reminder(s)
                              </span>
                            </div>
                          </div>
                        )}
                      </Card>
                    );
                  })}
              </div>
            </div>
          </div>
        </div>
      ) : (
        /* List View */
        <div className="flex-1 overflow-y-auto p-6">
          <div className="max-w-4xl mx-auto space-y-6">
            {/* Filters */}
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm">
                <Filter className="w-4 h-4 mr-2" />
                All Types
              </Button>
              <Button variant="outline" size="sm">High Priority</Button>
              <Button variant="outline" size="sm">With Reminders</Button>
            </div>

            {/* Events List */}
            <div className="space-y-3">
              {events
                .sort((a, b) => {
                  // Sort by date, then completed status
                  if (a.completed !== b.completed) return a.completed ? 1 : -1;
                  return a.date.localeCompare(b.date);
                })
                .map(event => {
                  const Icon = typeIcons[event.type];
                  const eventDate = new Date(event.date);
                  
                  return (
                    <Card 
                      key={event.id}
                      className={`p-6 bg-white border ${priorityColors[event.priority]} ${event.completed ? 'opacity-60' : ''}`}
                    >
                      <div className="flex items-start gap-4">
                        {/* Date Badge */}
                        <div className="flex-shrink-0 text-center">
                          <div className="w-16 h-16 bg-gray-100 rounded-lg flex flex-col items-center justify-center">
                            <div className="text-xs text-gray-500">
                              {monthNames[eventDate.getMonth()].substring(0, 3)}
                            </div>
                            <div className="text-xl font-semibold text-gray-900">
                              {eventDate.getDate()}
                            </div>
                          </div>
                        </div>

                        {/* Content */}
                        <div className="flex-1">
                          <div className="flex items-start justify-between mb-2">
                            <div className="flex items-center gap-2">
                              <h3 className={`font-semibold text-gray-900 ${event.completed ? 'line-through' : ''}`}>
                                {event.title}
                              </h3>
                              <Badge className={typeColors[event.type]}>
                                <Icon className="w-3 h-3 mr-1" />
                                {event.type}
                              </Badge>
                              {event.priority === "high" && (
                                <Badge variant="destructive">High Priority</Badge>
                              )}
                            </div>
                            <button className="text-gray-400 hover:text-gray-600">
                              {event.completed ? (
                                <CheckCircle2 className="w-5 h-5 text-green-600" />
                              ) : (
                                <Circle className="w-5 h-5" />
                              )}
                            </button>
                          </div>

                          {event.description && (
                            <p className="text-sm text-gray-600 mb-3">{event.description}</p>
                          )}

                          <div className="flex items-center gap-4 text-sm text-gray-600">
                            {event.time && (
                              <div className="flex items-center gap-1">
                                <Clock className="w-4 h-4" />
                                <span>{event.time}</span>
                              </div>
                            )}
                            {event.project && (
                              <div className="flex items-center gap-1 text-blue-600">
                                <Briefcase className="w-4 h-4" />
                                <span className="font-mono">{event.project}</span>
                              </div>
                            )}
                          </div>

                          {/* Reminders */}
                          {event.reminders && event.reminders.length > 0 && (
                            <div className="mt-3 pt-3 border-t border-gray-200">
                              <div className="flex items-center gap-2 mb-2">
                                <Bell className="w-4 h-4 text-gray-500" />
                                <span className="text-sm font-medium text-gray-700">Reminders</span>
                              </div>
                              <div className="flex flex-wrap gap-2">
                                {event.reminders.map(reminder => (
                                  <Badge 
                                    key={reminder.id}
                                    variant={reminder.enabled ? "default" : "outline"}
                                    className="text-xs"
                                  >
                                    {reminder.time}
                                  </Badge>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    </Card>
                  );
                })}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
