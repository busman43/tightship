import { Card } from "./ui/card";
import { 
  Inbox, 
  Clock, 
  AlertCircle, 
  CheckCircle2, 
  TrendingUp, 
  FileText,
  Calendar,
  ArrowRight
} from "lucide-react";
import { Button } from "./ui/button";

interface CommandCenterProps {
  onNavigate: (view: "inbox" | "proposals" | "projects" | "calendar") => void;
}

export function CommandCenter({ onNavigate }: CommandCenterProps) {
  return (
    <div className="h-full overflow-y-auto">
      <div className="p-8 max-w-7xl mx-auto space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-semibold text-gray-900 mb-2">Command Center</h1>
          <p className="text-gray-600">Your workspace at a glance – {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}</p>
        </div>

        {/* Key Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="p-6 bg-white border border-gray-200 hover:shadow-md transition-shadow cursor-pointer" onClick={() => onNavigate("inbox")}>
            <div className="flex items-start justify-between">
              <div>
                <div className="text-gray-600 text-sm mb-1">Inbox Zero Meter</div>
                <div className="text-3xl font-semibold text-gray-900">8</div>
                <div className="text-sm text-orange-600 mt-1">Files waiting</div>
              </div>
              <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <Inbox className="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </Card>

          <Card className="p-6 bg-white border border-gray-200 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
              <div>
                <div className="text-gray-600 text-sm mb-1">Drafts in Review</div>
                <div className="text-3xl font-semibold text-gray-900">3</div>
                <div className="text-sm text-yellow-600 mt-1">2 stuck &gt; 5 days</div>
              </div>
              <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <Clock className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
          </Card>

          <Card className="p-6 bg-white border border-gray-200 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
              <div>
                <div className="text-gray-600 text-sm mb-1">Due This Week</div>
                <div className="text-3xl font-semibold text-gray-900">5</div>
                <div className="text-sm text-red-600 mt-1">1 overdue</div>
              </div>
              <div className="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <AlertCircle className="w-6 h-6 text-red-600" />
              </div>
            </div>
          </Card>

          <Card className="p-6 bg-white border border-gray-200 hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between">
              <div>
                <div className="text-gray-600 text-sm mb-1">Ship List</div>
                <div className="text-3xl font-semibold text-gray-900">12</div>
                <div className="text-sm text-green-600 mt-1">Ready to submit</div>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <CheckCircle2 className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </Card>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Next Deadlines */}
          <Card className="bg-white border border-gray-200">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Calendar className="w-5 h-5 text-gray-600" />
                  <h2 className="font-semibold text-gray-900">Next Deadlines</h2>
                </div>
                <span className="text-sm text-gray-500">7 upcoming</span>
              </div>
            </div>
            <div className="divide-y divide-gray-100">
              <DeadlineItem 
                title="IPA Project Proposal – Full Application"
                date="Dec 22, 2025"
                status="approved"
                daysLeft={3}
                isOverdue={false}
              />
              <DeadlineItem 
                title="Quarterly Report – Education Project"
                date="Dec 20, 2025"
                status="draft"
                daysLeft={1}
                isOverdue={false}
              />
              <DeadlineItem 
                title="Budget Revision – Infrastructure Grant"
                date="Dec 18, 2025"
                status="review"
                daysLeft={0}
                isOverdue={true}
              />
              <DeadlineItem 
                title="Final Report – Capacity Building"
                date="Dec 28, 2025"
                status="draft"
                daysLeft={9}
                isOverdue={false}
              />
            </div>
          </Card>

          {/* Recent Activity */}
          <Card className="bg-white border border-gray-200">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <TrendingUp className="w-5 h-5 text-gray-600" />
                  <h2 className="font-semibold text-gray-900">Recent Activity</h2>
                </div>
                <span className="text-sm text-gray-500">Last 24h</span>
              </div>
            </div>
            <div className="divide-y divide-gray-100">
              <ActivityItem 
                action="uploaded"
                user="John Smith"
                item="Annex A2 - Partnership Agreement.pdf"
                time="2 hours ago"
                type="upload"
              />
              <ActivityItem 
                action="moved to Approved"
                user="Maria Garcia"
                item="Proposal – Digital Transformation"
                time="4 hours ago"
                type="status"
              />
              <ActivityItem 
                action="created revision r4"
                user="Ahmed Hassan"
                item="Budget Narrative – EU Call 2025"
                time="6 hours ago"
                type="revision"
              />
              <ActivityItem 
                action="commented on"
                user="Lisa Chen"
                item="Logframe – Rural Development"
                time="8 hours ago"
                type="comment"
              />
            </div>
          </Card>
        </div>

        {/* Ship List - Ready to Submit */}
        <Card className="bg-white border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <FileText className="w-5 h-5 text-gray-600" />
                <h2 className="font-semibold text-gray-900">Ship List – Ready to Submit</h2>
              </div>
              <Button variant="outline" size="sm">
                View All <ArrowRight className="w-4 h-4 ml-2" />
              </Button>
            </div>
          </div>
          <div className="divide-y divide-gray-100">
            <ShipListItem 
              title="Proposal – Regional Healthcare Network"
              project="EU Health 2025"
              status="Approved"
              deadline="Dec 25, 2025"
            />
            <ShipListItem 
              title="Final Report – Youth Employment Initiative"
              project="ESF+ Programme"
              status="Approved"
              deadline="Dec 30, 2025"
            />
            <ShipListItem 
              title="Grant Amendment Request – Infrastructure"
              project="IPA III Project"
              status="Approved"
              deadline="Jan 5, 2026"
            />
          </div>
        </Card>
      </div>
    </div>
  );
}

function DeadlineItem({ 
  title, 
  date, 
  status, 
  daysLeft, 
  isOverdue 
}: { 
  title: string; 
  date: string; 
  status: string; 
  daysLeft: number; 
  isOverdue: boolean;
}) {
  const statusColors = {
    draft: "bg-gray-100 text-gray-700",
    review: "bg-yellow-100 text-yellow-700",
    approved: "bg-green-100 text-green-700",
  };

  return (
    <div className="p-4 hover:bg-gray-50 transition-colors">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          <div className="font-medium text-gray-900 mb-1">{title}</div>
          <div className="flex items-center gap-2 text-sm">
            <span className={`px-2 py-0.5 rounded text-xs ${statusColors[status as keyof typeof statusColors]}`}>
              {status}
            </span>
            <span className="text-gray-500">•</span>
            <span className="text-gray-600">{date}</span>
          </div>
        </div>
        <div className={`text-sm font-medium ${isOverdue ? 'text-red-600' : 'text-gray-600'}`}>
          {isOverdue ? 'Overdue' : `${daysLeft}d left`}
        </div>
      </div>
    </div>
  );
}

function ActivityItem({ 
  action, 
  user, 
  item, 
  time, 
  type 
}: { 
  action: string; 
  user: string; 
  item: string; 
  time: string;
  type: string;
}) {
  return (
    <div className="p-4 hover:bg-gray-50 transition-colors">
      <div className="text-sm">
        <span className="font-medium text-gray-900">{user}</span>
        <span className="text-gray-600"> {action} </span>
        <span className="font-medium text-gray-900">{item}</span>
      </div>
      <div className="text-xs text-gray-500 mt-1">{time}</div>
    </div>
  );
}

function ShipListItem({ 
  title, 
  project, 
  status, 
  deadline 
}: { 
  title: string; 
  project: string; 
  status: string; 
  deadline: string;
}) {
  return (
    <div className="p-4 hover:bg-gray-50 transition-colors">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1">
          <div className="font-medium text-gray-900 mb-1">{title}</div>
          <div className="flex items-center gap-2 text-sm text-gray-600">
            <span>{project}</span>
            <span>•</span>
            <span>Due {deadline}</span>
          </div>
        </div>
        <Button size="sm" className="bg-blue-600 hover:bg-blue-700">
          Submit
        </Button>
      </div>
    </div>
  );
}