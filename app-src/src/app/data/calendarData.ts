export interface CalendarEvent {
  id: string;
  title: string;
  description?: string;
  date: string;
  time?: string;
  type: "deadline" | "meeting" | "reminder" | "milestone";
  project?: string;
  priority: "high" | "medium" | "low";
  completed?: boolean;
  reminders?: {
    id: string;
    time: string; // e.g., "1 day before", "2 hours before"
    enabled: boolean;
  }[];
}

export const mockCalendarEvents: CalendarEvent[] = [
  {
    id: "event-1",
    title: "IPA Project Proposal – Full Application Deadline",
    description: "Final submission deadline for Digital Transformation Initiative",
    date: "2025-12-22",
    time: "23:59",
    type: "deadline",
    project: "IPA-2025-DT",
    priority: "high",
    completed: false,
    reminders: [
      { id: "r1", time: "1 day before", enabled: true },
      { id: "r2", time: "1 hour before", enabled: true }
    ]
  },
  {
    id: "event-2",
    title: "Quarterly Report – Education Project",
    description: "Submit Q4 progress report",
    date: "2025-12-20",
    time: "17:00",
    type: "deadline",
    project: "ESF-2024-YE",
    priority: "high",
    completed: false,
    reminders: [
      { id: "r1", time: "2 days before", enabled: true }
    ]
  },
  {
    id: "event-3",
    title: "Budget Revision – Infrastructure Grant",
    date: "2025-12-18",
    time: "12:00",
    type: "deadline",
    project: "IPA-2025-INF",
    priority: "high",
    completed: true,
    reminders: []
  },
  {
    id: "event-4",
    title: "Team Sync – EU Projects",
    description: "Weekly standup to review proposal pipeline",
    date: "2025-12-20",
    time: "10:00",
    type: "meeting",
    priority: "medium",
    completed: false,
    reminders: [
      { id: "r1", time: "15 minutes before", enabled: true }
    ]
  },
  {
    id: "event-5",
    title: "Final Report – Capacity Building",
    date: "2025-12-28",
    time: "23:59",
    type: "deadline",
    priority: "medium",
    completed: false,
    reminders: [
      { id: "r1", time: "1 week before", enabled: true },
      { id: "r2", time: "3 days before", enabled: true }
    ]
  },
  {
    id: "event-6",
    title: "Regional Healthcare Network Submission",
    description: "Submit full application to EU Health Programme",
    date: "2025-12-25",
    time: "23:59",
    type: "deadline",
    project: "HEALTH-2025-RHN",
    priority: "high",
    completed: false,
    reminders: [
      { id: "r1", time: "3 days before", enabled: true },
      { id: "r2", time: "1 day before", enabled: true }
    ]
  },
  {
    id: "event-7",
    title: "Partner Meeting – Rural Development",
    description: "Review logframe and work packages with consortium",
    date: "2025-12-23",
    time: "14:00",
    type: "meeting",
    project: "RDP-2025",
    priority: "medium",
    completed: false,
    reminders: [
      { id: "r1", time: "1 day before", enabled: true }
    ]
  },
  {
    id: "event-8",
    title: "Mid-term Review Milestone",
    date: "2025-12-30",
    type: "milestone",
    project: "RDP-2025",
    priority: "medium",
    completed: false,
    reminders: []
  },
  {
    id: "event-9",
    title: "Document Archive Review",
    description: "Review and close completed projects from 2024",
    date: "2025-12-31",
    time: "16:00",
    type: "reminder",
    priority: "low",
    completed: false,
    reminders: [
      { id: "r1", time: "1 day before", enabled: true }
    ]
  },
  {
    id: "event-10",
    title: "Grant Amendment Request – Infrastructure",
    date: "2026-01-05",
    time: "23:59",
    type: "deadline",
    project: "IPA III Project",
    priority: "high",
    completed: false,
    reminders: [
      { id: "r1", time: "1 week before", enabled: true },
      { id: "r2", time: "2 days before", enabled: true }
    ]
  }
];
