import { 
  Home, 
  Inbox, 
  Folder, 
  Briefcase, 
  FileText, 
  File, 
  BookTemplate, 
  Users, 
  BarChart3,
  Calendar
} from "lucide-react";

type View = "command-center" | "inbox" | "spaces" | "projects" | "proposals" | "files" | "templates" | "crm" | "reports" | "calendar";

interface SidebarProps {
  currentView: View;
  onViewChange: (view: View) => void;
}

const navItems = [
  { id: "command-center" as View, label: "Command Center", icon: Home },
  { id: "inbox" as View, label: "File Inbox", icon: Inbox, badge: 8 },
  { id: "calendar" as View, label: "Calendar", icon: Calendar, badge: 5 },
  { id: "spaces" as View, label: "Spaces", icon: Folder },
  { id: "projects" as View, label: "Projects", icon: Briefcase },
  { id: "proposals" as View, label: "Proposals", icon: FileText },
  { id: "files" as View, label: "Files", icon: File },
  { id: "templates" as View, label: "Templates", icon: BookTemplate },
  { id: "crm" as View, label: "CRM", icon: Users },
  { id: "reports" as View, label: "Reports", icon: BarChart3 },
];

export function Sidebar({ currentView, onViewChange }: SidebarProps) {
  return (
    <aside className="w-64 bg-white border-r border-gray-200 flex flex-col">
      {/* Logo/Brand */}
      <div className="p-6 border-b border-gray-200">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg flex items-center justify-center">
            <Briefcase className="w-6 h-6 text-white" />
          </div>
          <div>
            <div className="font-semibold text-gray-900">TightShip</div>
            <div className="text-xs text-gray-500">Workspace</div>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 p-4 space-y-1 overflow-y-auto">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive = currentView === item.id;
          
          return (
            <button
              key={item.id}
              onClick={() => onViewChange(item.id)}
              className={`
                w-full flex items-center justify-between px-3 py-2.5 rounded-lg
                transition-all duration-150
                ${isActive 
                  ? 'bg-blue-50 text-blue-700' 
                  : 'text-gray-700 hover:bg-gray-50'
                }
              `}
            >
              <div className="flex items-center gap-3">
                <Icon className={`w-5 h-5 ${isActive ? 'text-blue-700' : 'text-gray-500'}`} />
                <span className="font-medium">{item.label}</span>
              </div>
              {item.badge && (
                <span className="px-2 py-0.5 bg-blue-600 text-white text-xs rounded-full">
                  {item.badge}
                </span>
              )}
            </button>
          );
        })}
      </nav>

      {/* User Section */}
      <div className="p-4 border-t border-gray-200">
        <div className="flex items-center gap-3 px-3 py-2">
          <div className="w-8 h-8 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
            <span className="text-white text-sm font-medium">JD</span>
          </div>
          <div className="flex-1">
            <div className="text-sm font-medium text-gray-900">Jane Doe</div>
            <div className="text-xs text-gray-500">Admin</div>
          </div>
        </div>
      </div>
    </aside>
  );
}